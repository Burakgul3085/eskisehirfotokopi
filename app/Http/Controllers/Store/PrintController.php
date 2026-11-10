<?php

namespace App\Http\Controllers\Store;

use App\Domain\Cart\Cart;
use App\Domain\Print\PrintCart;
use App\Domain\Print\PrintFiles;
use App\Domain\Print\PrintOptions;
use App\Domain\Print\PrintOwner;
use App\Domain\Print\PrintSettings;
use App\Domain\Print\SpecialQuoteRequired;
use App\Http\Controllers\Controller;
use App\Models\PrintFile;
use App\Support\Money;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fotokopi sayfası (/fotokopi): belge yükleme (parçalı), sayfa sayısı ve önizleme, seçeneklerle canlı fiyat, sepete
 * ekleme. Belgeye yalnızca aynı oturum erişir; fiyat her istekte sunucuda hesaplanır (PrintCart).
 */
class PrintController extends Controller
{
    public function __construct(private PrintOwner $owner, private PrintSettings $settings, private PrintFiles $files) {}

    public function show(): View
    {
        abort_unless($this->settings->enabled(), 404);
        $owner = $this->owner->find();
        $files = $owner ? PrintFile::where('owner', $owner)->whereIn('status', ['processing', 'ready', 'failed'])
            ->where('updated_at', '>', now()->subDay())->orderBy('id')->get() : collect();

        return view('store.print', [
            'print' => $this->settings->all(),
            'colors' => $this->settings->colors(),
            'config' => [
                'urls' => ['start' => route('print.start'), 'file' => route('print.status', '__T__')],
                'files' => $files->map(fn ($f) => $this->state($f))->values()->all(),
                'maxBytes' => $this->settings->maxBytes(),
                'maxFiles' => PrintFiles::MAX_FILES,
                'maxCopies' => $this->settings->maxCopies(),
                'colors' => $this->settings->colors(),
                'cartUrl' => route('cart.index'),
            ],
            'accept' => implode(',', array_map(fn ($e) => '.'.$e, array_keys(PrintFiles::TYPES))),
        ]);
    }

    public function start(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'], 'size' => ['required', 'integer', 'min:1'], 'consent' => ['accepted'],
        ], ['consent.accepted' => 'Belgeyi çoğaltma hakkınız olduğunu onaylayın.']);
        try {
            $file = $this->files->start($this->owner->key(), $data['name'], (int) $data['size']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->state($file) + ['chunk' => (int) config('print.chunk_bytes')]);
    }

    public function chunk(Request $request, string $token): JsonResponse
    {
        $file = $this->own($token);
        $request->validate(['offset' => ['required', 'integer', 'min:0'], 'chunk' => ['required', 'file', 'max:'.((int) config('print.chunk_bytes') / 1024 + 64)]]);
        try {
            $file = $this->files->appendChunk($file, (int) $request->input('offset'), $request->file('chunk'));
        } catch (DomainException $e) {
            if (str_starts_with($e->getMessage(), 'offset:')) {
                return response()->json(['received' => (int) substr($e->getMessage(), 7)], 409);
            }

            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($this->state($file));
    }

    public function status(string $token): JsonResponse
    {
        return response()->json($this->state($this->own($token)));
    }

    public function preview(string $token): Response
    {
        $file = $this->own($token);
        abort_unless($file->preview_path && Storage::disk('local')->exists($file->preview_path), 404);

        return Storage::disk('local')->response($file->preview_path, null, ['Cache-Control' => 'private, max-age=3600', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(string $token): JsonResponse
    {
        $this->files->delete($this->own($token));

        return response()->json(['deleted' => true]);
    }

    public function quote(Request $request, string $token, PrintCart $prints): JsonResponse
    {
        try {
            ['quote' => $quote, 'options' => $options] = $prints->quote($this->own($token), $request->all());
        } catch (ValidationException|DomainException $e) {
            return $this->error($e);
        }

        return response()->json(['quote' => $quote, 'total' => Money::format($quote['total']), 'summary' => $options->summary($quote['pages'], $quote['sheets'])]);
    }

    public function add(Request $request, string $token, PrintCart $prints, Cart $cart): JsonResponse
    {
        $file = $this->own($token);
        try {
            $prints->add($cart, $this->owner->key(), $file, $request->all());
        } catch (ValidationException|DomainException $e) {
            return $this->error($e);
        }

        return response()->json(['added' => true, 'count' => $cart->count(), 'cart_url' => route('cart.index')]);
    }

    private function error(ValidationException|DomainException $e): JsonResponse
    {
        return match (true) {
            $e instanceof ValidationException => response()->json(['message' => collect($e->errors())->flatten()->first(), 'errors' => $e->errors()], 422),
            $e instanceof SpecialQuoteRequired => response()->json(['message' => $e->getMessage(), 'special' => ['pages' => $e->pages, 'threshold' => $e->threshold]], 422),
            default => response()->json(['message' => $e->getMessage()], 422),
        };
    }

    private function own(string $token): PrintFile
    {
        $owner = $this->owner->find();
        abort_unless($owner && $this->settings->enabled(), 404);

        return PrintFile::where('token', $token)->where('owner', $owner)->where('status', '!=', 'deleted')->firstOrFail();
    }

    /** Tarayıcıya giden belge durumu. */
    private function state(PrintFile $file): array
    {
        return [
            'token' => $file->token, 'name' => $file->original_name, 'size' => $file->size, 'received' => $file->received,
            'status' => $file->status, 'pages' => $file->pages, 'error' => $file->error,
            'preview' => $file->preview_path ? route('print.preview', $file->token) : null,
            'layouts' => array_keys(PrintOptions::LAYOUTS),
        ];
    }
}
