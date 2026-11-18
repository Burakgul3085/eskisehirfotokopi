<?php

namespace App\Domain\Reviews;

use App\Mail\SiteMail;
use App\Models\Admin;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Support\Activity;
use App\Support\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Ürün değerlendirmeleri: kim yazabilir, gönderme/düzenleme, onay, ret, mağaza cevabı ve e-postalar.
 *
 * Kurallar: yalnız ürünü kendi üye hesabıyla sipariş edip teslim almış müşteri yazabilir; ürün başına bir
 * değerlendirme (düzenlenebilir). Varsayılan olarak yönetici onayıyla yayınlanır; düzenlenen değerlendirme yeniden
 * onaya düşer ve onaylanana kadar sayfada önceki hâli kalır.
 */
class Reviews
{
    public function __construct(private ReviewPhotos $photos) {}

    /**
     * @return array{state: 'guest'|'not_bought'|'not_delivered'|'ok', order_id: ?int, review: ?ProductReview}
     */
    public function eligibility(?User $user, Product $product): array
    {
        if (! $user) {
            return ['state' => 'guest', 'order_id' => null, 'review' => null];
        }
        $review = ProductReview::with('photos')->where('product_id', $product->id)->where('user_id', $user->id)->first();
        $orders = Order::where('user_id', $user->id)
            ->whereHas('items', fn ($q) => $q->where('kind', 'product')->where('product_id', $product->id))
            ->latest('id')->pluck('status', 'id');
        $delivered = $orders->filter(fn ($s) => $s === 'delivered')->keys()->first();
        $state = match (true) {
            $review !== null, $delivered !== null => 'ok',
            $orders->contains(fn ($s) => $s !== 'cancelled') => 'not_delivered',
            default => 'not_bought',
        };

        return ['state' => $state, 'order_id' => $delivered ?? $review?->order_id, 'review' => $review];
    }

    /**
     * Müşterinin değerlendirmesini kaydeder (ilk kez ya da düzenleme).
     *
     * @param  array{rating: int, title: ?string, body: string}  $data
     * @param  list<UploadedFile>  $newPhotos
     * @param  list<int>  $removePhotoIds
     */
    public function submit(User $user, Product $product, int $orderId, array $data, array $newPhotos = [], array $removePhotoIds = []): ProductReview
    {
        $auto = Settings::flag('reviews_auto_publish');
        $review = ProductReview::firstOrNew(['product_id' => $product->id, 'user_id' => $user->id]);
        $isEdit = $review->exists;
        $review->fill([
            'order_id' => $review->order_id ?? $orderId,
            'rating' => $data['rating'], 'title' => $data['title'] ?: null, 'body' => $data['body'],
            'status' => 'pending', 'reject_reason' => null, 'submitted_at' => now(),
        ])->save();

        if ($removePhotoIds) {
            $review->photos()->whereKey($removePhotoIds)->get()->each->delete();
        }
        if (Settings::flag('reviews_photos')) {
            $sort = (int) $review->photos()->max('sort');
            $room = max(0, ReviewPhotos::MAX - $review->photos()->count());
            foreach (array_slice($newPhotos, 0, $room) as $file) {
                $this->photos->store($review, $file, ++$sort, false);
            }
        }

        if ($auto) {
            $this->publish($review);
        }
        $review->load('product', 'user');

        $vars = $this->vars($review);
        // Müşteriye: onaysız yayında doğrudan "yayınlandı", aksi hâlde "bize ulaştı"
        if ($mail = SiteMail::notice($auto ? 'review_published' : 'review_received', $vars, $review)) {
            $mail->buttonUrl = $auto ? $review->url() : route('account.reviews');
            $mail->quote = $this->quote($review);
            Mail::to($user->email, $user->name)->send($mail);
        }
        if (($admins = SiteMail::adminRecipients()) && ($mail = SiteMail::notice($isEdit ? 'review_edited_admin' : 'review_admin', $vars, $review))) {
            $mail->quote = $this->quote($review);
            $mail->facts = array_values(array_filter([
                ['Ürün', $product->title], ['Puan', self::stars($review->rating)], ['Müşteri', $user->name.' · '.$user->email],
                ($n = $review->photos()->count()) ? ['Fotoğraf', $n.' adet'] : null,
            ]));
            $mail->buttonUrl = route('admin.reviews.show', $review);
            Mail::to($admins)->send($mail);
        }

        return $review;
    }

    /** Yönetici onayı: son gönderilen hâli yayına alır; cevap yazıldıysa müşteriye tek e-postada ikisi birden gider. */
    public function approve(ProductReview $review, ?Admin $admin, ?string $reply = null, bool $notify = true): void
    {
        $reply = trim((string) $reply);
        if ($reply !== '') {
            $review->fill(['reply' => $reply, 'replied_at' => now(), 'reply_admin_id' => $admin?->id]);
        }
        $this->publish($review);
        Activity::log('review.approved', $review);

        if ($notify) {
            $review->loadMissing('product', 'user');
            $mail = SiteMail::notice($reply !== '' ? 'review_published_reply' : 'review_published', $this->vars($review), $review);
            if ($mail) {
                $mail->quote = $reply !== '' ? $reply : $this->quote($review);
                $mail->buttonUrl = $review->url();
                Mail::to($review->user->email, $review->user->name)->send($mail);
            }
        }
    }

    /**
     * Ret. Onay bekleyen bir düzenlemede yayındaki önceki hâl korunur; yayındaki değerlendirme reddedilirse sayfadan kalkar.
     */
    public function reject(ProductReview $review, ?string $reason, bool $notify = true): void
    {
        $unpublish = $review->status === 'approved';
        $review->fill(['status' => 'rejected', 'reject_reason' => trim((string) $reason) ?: null]);
        if ($unpublish) {
            $review->fill(['published_at' => null, 'published_rating' => null, 'published_title' => null, 'published_body' => null]);
            $review->photos()->update(['is_approved' => false]);
        }
        $review->save();
        $this->refreshRating($review->product_id);
        Activity::log('review.rejected', $review, ['reason' => $review->reject_reason]);

        if ($notify) {
            $review->loadMissing('product', 'user');
            if ($mail = SiteMail::notice('review_rejected', $this->vars($review), $review)) {
                $mail->quote = $review->reject_reason;
                $mail->buttonUrl = route('product.show', $review->product).'#degerlendirme-yaz';
                Mail::to($review->user->email, $review->user->name)->send($mail);
            }
        }
    }

    /** Mağaza cevabı (boş metin cevabı kaldırır). Müşteriye yalnız yayındaki değerlendirmede ve istenirse e-posta gider. */
    public function reply(ProductReview $review, ?string $text, ?Admin $admin, bool $notify): void
    {
        $text = trim((string) $text);
        $review->update($text === ''
            ? ['reply' => null, 'replied_at' => null, 'reply_admin_id' => null]
            : ['reply' => $text, 'replied_at' => now(), 'reply_admin_id' => $admin?->id]);
        Activity::log($text === '' ? 'review.reply_removed' : 'review.replied', $review);

        if ($text !== '' && $notify && $review->isPublished()) {
            $review->loadMissing('product', 'user');
            if ($mail = SiteMail::notice('review_reply', $this->vars($review), $review)) {
                $mail->quote = $text;
                $mail->buttonUrl = $review->url();
                Mail::to($review->user->email, $review->user->name)->send($mail);
            }
        }
    }

    public function destroy(ProductReview $review): void
    {
        $productId = $review->product_id;
        DB::transaction(function () use ($review) {
            $review->photos->each->delete();
            $review->delete();
        });
        $this->refreshRating($productId);
    }

    /** Ürün kartı ve başlık altı için ortalama puan ve sayı (yalnız yayındakiler). */
    public function refreshRating(int $productId): void
    {
        $row = ProductReview::published()->where('product_id', $productId)->selectRaw('count(*) as c, avg(published_rating) as a')->first();
        DB::table('products')->where('id', $productId)->update([
            'rating_count' => (int) $row->c,
            'rating_avg' => $row->c ? round((float) $row->a, 1) : null,
        ]);
    }

    /**
     * Sipariş teslim edilince bir kez: üyenin henüz değerlendirmediği ürünler için davet e-postası.
     */
    public function invite(Order $order): void
    {
        if (! $order->user_id || $order->review_invited_at || ! Settings::flag('reviews_enabled')) {
            return;
        }
        $order->forceFill(['review_invited_at' => now()])->saveQuietly();
        $user = $order->user;
        $reviewed = ProductReview::where('user_id', $order->user_id)->pluck('product_id')->all();
        $products = Product::visible()->whereIn('id', OrderItem::where('order_id', $order->id)->where('kind', 'product')->pluck('product_id')->filter())
            ->whereNotIn('id', $reviewed)->with('publisher:id,name')->get();
        if (! $user || $products->isEmpty()) {
            return;
        }
        $mail = SiteMail::notice('review_invite', ['ad' => $user->name, 'siparis' => $order->number, 'urun' => $products->first()->title], $order);
        if ($mail) {
            $mail->links = $products->map(fn (Product $p) => [$p->title, (string) $p->publisher?->name, route('product.show', $p).'#degerlendirme-yaz'])->all();
            $mail->buttonUrl = route('account.reviews');
            Mail::to($user->email, $user->name)->send($mail);
        }
    }

    public static function stars(int $rating): string
    {
        $rating = max(0, min(5, $rating));

        return str_repeat('★', $rating).str_repeat('☆', 5 - $rating).' ('.$rating.'/5)';
    }

    private function publish(ProductReview $review): void
    {
        $review->fill([
            'status' => 'approved', 'reject_reason' => null,
            'published_rating' => $review->rating, 'published_title' => $review->title, 'published_body' => $review->body,
            'published_at' => $review->published_at ?? now(),
        ])->save();
        $review->photos()->update(['is_approved' => true]);
        $this->refreshRating($review->product_id);
    }

    /** @return array<string, string> */
    private function vars(ProductReview $review): array
    {
        return ['ad' => (string) $review->user?->name, 'urun' => (string) $review->product?->title, 'puan' => (string) $review->rating];
    }

    private function quote(ProductReview $review): string
    {
        return trim(self::stars($review->rating)."\n".($review->title ? $review->title."\n" : '').$review->body);
    }
}
