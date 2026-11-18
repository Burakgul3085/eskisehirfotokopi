<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Reviews\Questions;
use App\Http\Controllers\Controller;
use App\Models\ProductQuestion;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Yönetim › Sorular: cevapla ve yayınla / yalnız e-postayla cevapla, yayından kaldır, sil (silmede e-posta gitmez). */
class QuestionController extends Controller
{
    public const TABS = ['bekleyen' => 'Cevap bekleyen', 'yayinda' => 'Yayında', 'eposta' => 'Yalnız e-posta', 'tumu' => 'Tümü'];

    public function index(Request $request): View
    {
        $status = array_key_exists($request->query('durum'), self::TABS) ? $request->query('durum') : 'bekleyen';
        $scopes = [
            'bekleyen' => fn ($q) => $q->whereNull('answered_at'),
            'yayinda' => fn ($q) => $q->whereNotNull('answered_at')->where('is_public', true),
            'eposta' => fn ($q) => $q->whereNotNull('answered_at')->where('is_public', false),
        ];
        $query = ProductQuestion::query()->with('product:id,title,slug')->search($request->query('q'));
        if (isset($scopes[$status])) {
            $scopes[$status]($query);
        }

        return view('admin.questions.index', [
            'questions' => $query->latest('id')->paginate(30)->withQueryString(),
            'status' => $status,
            'counts' => collect($scopes)->map(fn ($scope) => $scope(ProductQuestion::query())->count())->put('tumu', ProductQuestion::count()),
        ]);
    }

    public function show(ProductQuestion $question): View
    {
        return view('admin.questions.show', [
            'question' => $question->load('product.publisher', 'user', 'answerer'),
            'others' => ProductQuestion::where('product_id', $question->product_id)->whereKeyNot($question->id)->latest('id')->limit(6)->get(),
        ]);
    }

    public function answer(Request $request, ProductQuestion $question, Questions $questions): RedirectResponse
    {
        $data = $request->validate([
            'answer' => ['required', 'string', 'min:2', 'max:3000'],
            'publish' => ['required', 'in:0,1'],
            'notify' => ['nullable', 'boolean'],
        ]);
        $first = ! $question->isAnswered();
        $questions->answer($question, $data['answer'], $data['publish'] === '1', $request->user('admin'), $request->boolean('notify'));
        $mailed = $first || $request->boolean('notify');
        $message = ($first ? 'Soru cevaplandı' : 'Cevap güncellendi').($data['publish'] === '1' ? ' ve ürün sayfasında yayınlandı' : '').($mailed ? '; soran kişiye e-posta gönderildi.' : '.');

        if ($first && ($next = ProductQuestion::whereNull('answered_at')->oldest('id')->first())) {
            return redirect()->route('admin.questions.show', $next)->with('status', $message.' Sıradaki cevap bekleyen soru açıldı.');
        }

        return redirect()->route('admin.questions.show', $question)->with('status', $message);
    }

    public function visibility(Request $request, ProductQuestion $question, Questions $questions): RedirectResponse
    {
        $questions->setPublic($question, $request->boolean('public'));

        return back()->with('status', $question->is_public ? 'Soru ürün sayfasında yayınlandı.' : 'Soru ürün sayfasından kaldırıldı.');
    }

    public function destroy(ProductQuestion $question): RedirectResponse
    {
        Activity::log('question.deleted', $question, ['product_id' => $question->product_id, 'email' => $question->email]);
        $question->delete();

        return redirect()->route('admin.questions.index')->with('status', 'Soru silindi; kimseye e-posta gönderilmedi.');
    }
}
