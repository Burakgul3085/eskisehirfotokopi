<?php

namespace App\Http\Controllers\Store;

use App\Domain\Reviews\PhotoProcessingFailed;
use App\Domain\Reviews\ReviewPhotos;
use App\Domain\Reviews\Reviews;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductReview;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/** Ürün sayfasından değerlendirme yazma, düzenleme ve silme (yalnız ürünü teslim almış üyeler). */
class ReviewController extends Controller
{
    public function store(Request $request, Product $product, Reviews $reviews): RedirectResponse
    {
        abort_unless($product->status === 'active' && Settings::flag('reviews_enabled'), 404);
        $user = $request->user('web');
        $eligibility = $reviews->eligibility($user, $product);
        $back = fn () => redirect()->to(route('product.show', $product).'#degerlendirme-yaz');
        if ($eligibility['state'] !== 'ok') {
            return $back()->withErrors(['review' => Settings::get($eligibility['state'] === 'not_delivered' ? 'reviews_not_delivered' : 'reviews_not_bought')]);
        }

        $existing = $eligibility['review'];
        $removable = $existing ? $existing->photos->pluck('id')->all() : [];
        $validator = Validator::make($request->all(), [
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:'.ReviewPhotos::MAX],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=9000,max_height=9000'],
            'remove_photos' => ['nullable', 'array'],
            'remove_photos.*' => ['integer', 'in:'.implode(',', $removable ?: [0])],
        ], [
            'rating.required' => 'Yıldızlardan birini seçerek puan verin.',
            'body.min' => 'Değerlendirmeniz en az 10 karakter olmalı.',
            'photos.max' => 'En fazla '.ReviewPhotos::MAX.' fotoğraf ekleyebilirsiniz.',
            'photos.*.image' => 'Yalnız fotoğraf (JPG, PNG, WEBP) yükleyebilirsiniz.',
            'photos.*.mimes' => 'Yalnız JPG, PNG veya WEBP yükleyebilirsiniz.',
            'photos.*.max' => 'Her fotoğraf en fazla 5 MB olabilir.',
        ], ['rating' => 'puan', 'title' => 'başlık', 'body' => 'değerlendirme']);
        if ($validator->fails()) {
            return $back()->withErrors($validator)->withInput();
        }
        $data = $validator->validated();
        $keep = count($removable) - count($data['remove_photos'] ?? []);
        if ($keep + count($request->file('photos', [])) > ReviewPhotos::MAX) {
            return $back()->withErrors(['photos' => 'En fazla '.ReviewPhotos::MAX.' fotoğraf olabilir; önce mevcut fotoğraflardan birini kaldırın.'])->withInput();
        }

        try {
            $reviews->submit($user, $product, (int) $eligibility['order_id'], [
                'rating' => (int) $data['rating'], 'title' => $data['title'] ?? null, 'body' => trim($data['body']),
            ], $request->file('photos', []), array_map('intval', $data['remove_photos'] ?? []));
        } catch (PhotoProcessingFailed) {
            return $back()->withErrors(['photos' => 'Fotoğraflardan biri işlenemedi; başka bir fotoğraf deneyin.'])->withInput();
        }

        return redirect()->to(route('product.show', $product).'#degerlendirmeler')
            ->with('review_status', Settings::get(Settings::flag('reviews_auto_publish') ? 'reviews_thanks_auto' : 'reviews_thanks'));
    }

    public function destroy(Request $request, Product $product, Reviews $reviews): RedirectResponse
    {
        $review = ProductReview::where('product_id', $product->id)->where('user_id', $request->user('web')->id)->firstOrFail();
        $reviews->destroy($review);

        return redirect()->to(route('product.show', $product).'#degerlendirmeler')->with('review_status', Settings::get('reviews_deleted'));
    }
}
