<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\ProductReview;
use App\Models\ReviewPhoto;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Yönetim › Değerlendirmeler: onay, ret (isteğe bağlı nedenle), mağaza cevabı, fotoğraf silme. */
class ReviewController extends Controller
{
    public const TABS = ['pending' => 'Onay bekleyen', 'approved' => 'Yayında', 'rejected' => 'Reddedilen', 'tumu' => 'Tümü'];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('durum'), self::TABS) ? $request->query('durum') : 'pending';
        $reviews = ProductReview::query()->with('product:id,title,slug,image_key', 'user:id,name,email')->withCount('photos')
            ->search($request->query('q'))
            ->when($status !== 'tumu', fn ($q) => $q->where('status', $status))
            ->when($request->filled('puan'), fn ($q) => $q->where('rating', (int) $request->query('puan')))
            ->latest('submitted_at')->paginate(30)->withQueryString();

        return view('admin.reviews.index', [
            'reviews' => $reviews,
            'status' => $status,
            'counts' => ProductReview::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
        ]);
    }

    public function show(ProductReview $review): View
    {
        return view('admin.reviews.show', [
            'review' => $review->load('product.publisher', 'user', 'order', 'photos', 'replyAdmin'),
            'others' => ProductReview::where('user_id', $review->user_id)->whereKeyNot($review->id)->with('product:id,title')->latest('submitted_at')->limit(5)->get(),
        ]);
    }

    public function approve(Request $request, ProductReview $review, Reviews $reviews): RedirectResponse
    {
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:2000'], 'notify' => ['nullable', 'boolean']]);
        $reviews->approve($review, $request->user('admin'), $data['reply'] ?? null, $request->boolean('notify'));

        return $this->next($review, 'Değerlendirme yayınlandı'.($request->boolean('notify') ? '; müşteriye e-posta gönderildi.' : '.'));
    }

    public function reject(Request $request, ProductReview $review, Reviews $reviews): RedirectResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300'], 'notify' => ['nullable', 'boolean']]);
        $reviews->reject($review, $data['reason'] ?? null, $request->boolean('notify'));

        return $this->next($review, 'Değerlendirme reddedildi'.($request->boolean('notify') ? '; müşteriye e-posta gönderildi.' : '.'));
    }

    public function reply(Request $request, ProductReview $review, Reviews $reviews): RedirectResponse
    {
        $data = $request->validate(['reply' => ['nullable', 'string', 'max:2000'], 'notify' => ['nullable', 'boolean']]);
        $reviews->reply($review, $data['reply'] ?? null, $request->user('admin'), $request->boolean('notify'));

        return redirect()->route('admin.reviews.show', $review)->with('status', blank($data['reply'] ?? null) ? 'Cevap kaldırıldı.' : 'Cevap kaydedildi; değerlendirmenin altında görünür.');
    }

    public function destroyPhoto(ProductReview $review, ReviewPhoto $photo, Reviews $reviews): RedirectResponse
    {
        abort_unless($photo->review_id === $review->id, 404);
        $photo->delete();
        Activity::log('review.photo_deleted', $review);

        return redirect()->route('admin.reviews.show', $review)->with('status', 'Fotoğraf silindi.');
    }

    public function destroy(ProductReview $review, Reviews $reviews): RedirectResponse
    {
        Activity::log('review.deleted', $review, ['product_id' => $review->product_id, 'user_id' => $review->user_id]);
        $reviews->destroy($review);

        return redirect()->route('admin.reviews.index')->with('status', 'Değerlendirme silindi.');
    }

    /** İşlemden sonra sıradaki onay bekleyen değerlendirmeye geçilir; yoksa listeye dönülür. */
    private function next(ProductReview $review, string $message): RedirectResponse
    {
        $next = ProductReview::where('status', 'pending')->whereKeyNot($review->id)->oldest('submitted_at')->first();

        return ($next ? redirect()->route('admin.reviews.show', $next) : redirect()->route('admin.reviews.index'))
            ->with('status', $message.($next ? ' Sıradaki onay bekleyen değerlendirme açıldı.' : ''));
    }
}
