<?php

namespace App\Http\Controllers\Store;

use App\Domain\Reviews\Questions;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * Ürün sayfasından soru sorma: satın alma ve üyelik gerekmez. Soran kişiye "sorunuz ulaştı" e-postası gittiği için
 * başkasının adresine e-posta yağdırılmasın diye aynı bağlantı ve aynı e-posta için saatte en fazla 3 soru kabul edilir.
 */
class QuestionController extends Controller
{
    private const PER_HOUR = 3;

    public function store(Request $request, Product $product, Questions $questions): RedirectResponse
    {
        abort_unless($product->status === 'active' && Settings::flag('questions_enabled'), 404);
        $back = fn () => redirect()->to(route('product.show', $product).'#soru-sor');
        // Bot tuzağı: görünmeyen alan doluysa sessizce başarılı say.
        if (filled($request->input('website'))) {
            return $back()->with('question_status', Settings::get('questions_thanks'));
        }
        $user = $request->user('web');
        $request->merge([
            'email' => mb_strtolower(trim((string) ($request->input('email') ?: $user?->email))),
            'name' => trim((string) ($request->input('name') ?: $user?->name)),
        ]);
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'body' => ['required', 'string', 'min:10', 'max:1000'],
            'consent' => ['accepted'],
        ], [
            'consent.accepted' => 'Sorunuzu cevaplayabilmemiz için onay kutusunu işaretleyin.',
            'body.min' => 'Sorunuz en az 10 karakter olmalı.',
        ], ['name' => 'ad soyad', 'email' => 'e-posta', 'body' => 'soru']);
        if ($validator->fails()) {
            return $back()->withErrors($validator, 'question')->withInput();
        }
        $data = $validator->validated();

        $keys = ['product-question:ip:'.$request->ip(), 'product-question:email:'.sha1($data['email'])];
        foreach ($keys as $key) {
            if (RateLimiter::tooManyAttempts($key, self::PER_HOUR)) {
                $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

                return $back()->withErrors(['body' => "Kısa sürede çok fazla soru gönderildi. Lütfen {$minutes} dakika sonra tekrar deneyin."], 'question')->withInput();
            }
        }
        foreach ($keys as $key) {
            RateLimiter::hit($key, 3600);
        }

        $questions->ask($product, ['name' => $data['name'], 'email' => $data['email'], 'body' => trim($data['body'])], $user?->id, $request->ip());

        return $back()->with('question_status', Settings::get('questions_thanks'));
    }
}
