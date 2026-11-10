<?php

namespace Tests\Feature;

use App\Domain\Catalog\CategoryTree;
use App\Domain\Delivery\DeliveryRules;
use App\Events\OrderPlaced;
use App\Events\OrderStatusChanged;
use App\Models\Admin;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Models\Product;
use App\Models\Publisher;
use App\Models\User;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    private Product $book;

    private Product $cheap;

    private DeliveryDistrict $odunpazari;

    protected function setUp(): void
    {
        parent::setUp();
        $category = app(CategoryTree::class)->ensurePath(['YKS', 'TYT']);
        $publisher = Publisher::create(['name' => 'Palme', 'slug' => 'palme', 'match_key' => 'palme', 'is_active' => true]);
        $this->book = Product::create(['title' => 'TYT Matematik', 'slug' => 'tyt-matematik', 'category_id' => $category->id, 'publisher_id' => $publisher->id,
            'price_bw' => 150, 'price_color' => 260, 'status' => 'active']);
        $this->cheap = Product::create(['title' => 'Deneme Kitapçığı', 'slug' => 'deneme', 'category_id' => $category->id, 'price_bw' => 40, 'status' => 'active']);
        $this->odunpazari = DeliveryDistrict::where('name', 'Odunpazarı')->firstOrFail();
    }

    private function checkoutData(array $override = []): array
    {
        return $override + [
            'name' => 'Ayşe Yılmaz', 'phone' => '0532 123 45 67', 'email' => 'ayse@example.com',
            'district_id' => $this->odunpazari->id, 'neighborhood' => 'Vişnelik', 'address' => 'Atatürk Cad. No: 12 Daire 4',
            'slot' => '10:00 - 13:00', 'payment_method' => 'cash', 'terms' => '1',
        ];
    }

    public function test_cart_add_update_remove(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'color', 'quantity' => 2])->assertSessionHas('status');
        $this->get(route('cart.index'))->assertOk()->assertSee('TYT Matematik')->assertSee('520,00 ₺')->assertSee('Renkli');

        $key = 'product:'.$this->book->id.':color';
        $this->patch(route('cart.update'), ['key' => $key, 'quantity' => 3]);
        $this->get(route('cart.index'))->assertSee('780,00 ₺');

        $this->delete(route('cart.remove'), ['key' => $key]);
        $this->get(route('cart.index'))->assertSee('Sepetiniz boş');
    }

    public function test_unpriced_or_hidden_product_cannot_be_added(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->cheap->id, 'print_type' => 'color'])->assertSessionHasErrors('cart');
        $this->book->update(['status' => 'draft']);
        $this->postJson(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw'])->assertStatus(422);
    }

    public function test_guest_places_order_with_server_side_totals(): void
    {
        Event::fake([OrderPlaced::class]);
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw', 'quantity' => 1]);
        $this->get(route('checkout.show'))->assertOk()->assertSee('Odunpazarı')->assertDontSee('>Alpu<', false);

        $response = $this->post(route('checkout.store'), $this->checkoutData(['subtotal' => 1, 'total' => 1]));
        $order = Order::firstOrFail();
        $response->assertRedirect(route('checkout.done', $order));

        $this->assertSame('150.00', $order->subtotal);
        $this->assertSame('40.00', $order->delivery_fee);
        $this->assertSame('190.00', $order->total);
        $this->assertSame('05321234567', $order->phone);
        $this->assertNull($order->user_id);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, $this->book->fresh()->sales_count);
        $this->assertSame(1, $order->history()->count());
        Event::assertDispatched(OrderPlaced::class);

        $this->get(route('checkout.done', $order))->assertOk()->assertSee($order->number);
        $this->get(route('cart.index'))->assertSee('Sepetiniz boş');
    }

    public function test_free_delivery_above_threshold_and_neighborhood_fee(): void
    {
        $this->odunpazari->neighborhoods()->createMany([['name' => 'Vişnelik', 'fee' => 60, 'is_active' => true], ['name' => 'Kapalı Mah', 'is_active' => false]]);
        $rules = app(DeliveryRules::class);
        $this->assertSame(60.0, $rules->fee($this->odunpazari, 'Vişnelik', 200));
        $this->assertSame(0.0, $rules->fee($this->odunpazari, 'Vişnelik', 500));

        // Listede olmayan veya kapalı mahalleye sipariş verilemez.
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'color', 'quantity' => 2]);
        $this->post(route('checkout.store'), $this->checkoutData(['neighborhood' => 'Kapalı Mah']))->assertSessionHasErrors('neighborhood');

        $this->post(route('checkout.store'), $this->checkoutData());
        $this->assertSame('0.00', Order::firstOrFail()->delivery_fee);
    }

    public function test_minimum_order_and_closed_district_are_enforced(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->cheap->id, 'print_type' => 'bw']);
        $this->post(route('checkout.store'), $this->checkoutData())->assertSessionHasErrors('cart');

        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw']);
        $alpu = DeliveryDistrict::where('name', 'Alpu')->firstOrFail();
        $this->post(route('checkout.store'), $this->checkoutData(['district_id' => $alpu->id]))->assertSessionHasErrors('district_id');

        Settings::put(['orders_open' => '0']);
        $this->post(route('checkout.store'), $this->checkoutData())->assertStatus(503);
        $this->assertSame(0, Order::count());
    }

    public function test_tracking_by_number_and_phone(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw']);
        $this->post(route('checkout.store'), $this->checkoutData());
        $order = Order::firstOrFail();

        $this->post(route('orders.track'), ['number' => strtolower($order->number), 'phone' => '0532 000 00 00'])->assertSessionHasErrors('number');
        $this->post(route('orders.track'), ['number' => strtolower($order->number), 'phone' => '+90 532 123 45 67'])->assertRedirect($order->trackingUrl());
        $this->get($order->trackingUrl())->assertOk()->assertSee($order->number)->assertSee('Siparişiniz alındı');
        $this->get(route('orders.track.show', ['order' => $order->number, 'token' => 'yanlis']))->assertNotFound();
    }

    public function test_member_order_saves_address_and_shows_in_account(): void
    {
        $user = User::factory()->create(['phone' => '05551112233']);
        $other = User::factory()->create();
        $this->actingAs($user);
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw', 'quantity' => 2]);
        $this->post(route('checkout.store'), $this->checkoutData(['email' => null, 'save_address' => '1']))->assertSessionHasNoErrors();

        $order = Order::firstOrFail();
        $this->assertSame($user->id, $order->user_id);
        $this->assertSame($user->email, $order->email);
        $this->assertSame(1, $user->addresses()->count());
        $this->get(route('account.orders'))->assertOk()->assertSee($order->number);
        $this->get(route('account.orders.show', $order))->assertOk();

        $this->post(route('account.orders.reorder', $order))->assertRedirect(route('cart.index'));
        $this->get(route('cart.index'))->assertSee('TYT Matematik');

        $this->actingAs($other)->get(route('account.orders.show', $order))->assertNotFound();
    }

    public function test_addresses_and_favorites(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $data = ['title' => 'Ev', 'name' => 'Ali Veli', 'phone' => '05321234567', 'district_id' => $this->odunpazari->id, 'neighborhood' => 'Yenidoğan', 'address' => 'Uzun bir sokak adı no 5'];
        $this->post(route('account.addresses.store'), $data)->assertRedirect(route('account.addresses.index'));
        $this->post(route('account.addresses.store'), ['title' => 'İş'] + $data + ['is_default' => '1']);
        $this->assertSame('İş', $user->addresses()->where('is_default', true)->value('title'));

        $alpu = DeliveryDistrict::where('name', 'Alpu')->firstOrFail();
        $this->post(route('account.addresses.store'), ['district_id' => $alpu->id] + $data)->assertSessionHasErrors('district_id');

        $this->postJson(route('favorites.toggle', $this->book))->assertJson(['favorite' => true]);
        $this->get(route('account.favorites'))->assertSee('TYT Matematik');
        $this->postJson(route('favorites.toggle', $this->book))->assertJson(['favorite' => false]);
    }

    public function test_admin_changes_status_and_cancel_restores_sales(): void
    {
        Event::fake([OrderStatusChanged::class]);
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw', 'quantity' => 3]);
        $this->post(route('checkout.store'), $this->checkoutData());
        $order = Order::firstOrFail();
        $this->assertSame(3, $this->book->fresh()->sales_count);

        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin');
        $this->get(route('admin.orders.index'))->assertOk()->assertSee($order->number);
        $this->get(route('admin.orders.index', ['q' => '1234567']))->assertSee($order->number);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Ayşe Yılmaz');
        $this->get(route('admin.orders.slip', $order))->assertOk()->assertSee('Tahsil edilecek');

        $this->put(route('admin.orders.status', $order), ['status' => 'preparing', 'notify' => '1'])->assertSessionHasNoErrors();
        $this->assertSame('preparing', $order->fresh()->status);
        Event::assertDispatched(OrderStatusChanged::class, fn ($e) => $e->notify === true);

        $this->put(route('admin.orders.status', $order), ['status' => 'cancelled'])->assertSessionHasErrors('note');
        $this->put(route('admin.orders.status', $order), ['status' => 'cancelled', 'note' => 'Müşteri vazgeçti']);
        $this->assertSame(0, $this->book->fresh()->sales_count);
        $this->put(route('admin.orders.status', $order), ['status' => 'preparing'])->assertSessionHasErrors('status');
        $this->assertSame(3, $order->history()->count());

        $this->get($order->trackingUrl())->assertSee('Müşteri vazgeçti');
    }

    public function test_daily_delivery_list(): void
    {
        $this->post(route('cart.add'), ['product_id' => $this->book->id, 'print_type' => 'bw']);
        $this->post(route('checkout.store'), $this->checkoutData());
        $order = Order::firstOrFail();

        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.orders.deliveries', ['gun' => $order->delivery_date->toDateString()]))->assertOk()->assertSee($order->number)->assertSee('Odunpazarı');
        $this->get(route('admin.orders.deliveries', ['gun' => $order->delivery_date->toDateString(), 'yazdir' => 1]))->assertOk()->assertSee('Atatürk Cad.');
    }

    public function test_admin_manages_districts_and_delivery_settings(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->get(route('admin.districts.index'))->assertOk()->assertSee('Tepebaşı');

        $this->put(route('admin.districts.update', $this->odunpazari), [
            'name' => 'Odunpazarı', 'fee' => '45,50', 'free_threshold' => '', 'min_order' => '150', 'extra_days' => 0, 'is_active' => '1',
            'neighborhoods' => "Vişnelik\nYenidoğan|60,00\nKurtuluş|kapalı\n\nvişnelik",
        ])->assertRedirect(route('admin.districts.index'));
        $d = $this->odunpazari->fresh();
        $this->assertSame('45.50', $d->fee);
        $this->assertSame('150.00', $d->min_order);
        $this->assertSame(3, $d->neighborhoods()->count());
        $this->assertSame('60.00', $d->neighborhoods()->where('name', 'Yenidoğan')->value('fee'));
        $this->assertFalse((bool) $d->neighborhoods()->where('name', 'Kurtuluş')->value('is_active'));
        $this->get(route('admin.districts.edit', $d))->assertOk()->assertSee('Yenidoğan|60,00');

        $this->post(route('admin.districts.toggle', $d));
        $this->assertFalse($d->fresh()->is_active);

        $this->put(route('admin.settings.update', 'teslimat'), [
            'orders_open' => '1', 'delivery_free_threshold' => '750', 'delivery_min_order' => '', 'delivery_cutoff' => '14:30',
            'delivery_same_day' => '0', 'delivery_closed_days' => ['6', '7'], 'delivery_slots' => '', 'payment_card' => '1',
        ])->assertSessionHasNoErrors();
        $this->assertSame('750.00', Settings::get('delivery_free_threshold'));
        $this->assertNull(Settings::get('delivery_min_order'));
        $this->assertSame('6,7', Settings::get('delivery_closed_days'));
        $this->assertSame('0', Settings::get('payment_cash'));
        $this->assertSame([], app(DeliveryRules::class)->slots());

        $this->put(route('admin.settings.update', 'teslimat'), ['orders_open' => '1', 'delivery_cutoff' => '14:30', 'delivery_same_day' => '0'])
            ->assertSessionHasErrors('payment_cash');
    }

    public function test_delivery_estimate_skips_closed_days_and_cutoff(): void
    {
        Settings::put(['delivery_cutoff' => '15:00', 'delivery_same_day' => '1', 'delivery_closed_days' => '7']);
        $rules = app(DeliveryRules::class);
        $saturdayMorning = CarbonImmutable::parse('2026-11-14 10:00'); // Cumartesi
        $this->assertTrue($rules->estimate(null, $saturdayMorning)->isSameDay($saturdayMorning));
        // Kesim sonrası ertesi gün Pazar kapalı → Pazartesi.
        $this->assertSame('2026-11-16', $rules->estimate(null, $saturdayMorning->setTime(16, 0))->toDateString());
        // Uzak ilçe: +1 açık gün.
        $far = new DeliveryDistrict(['extra_days' => 1]);
        $this->assertSame('2026-11-16', $rules->estimate($far, $saturdayMorning)->toDateString());
        $this->assertSame('Bugün', $rules->label($saturdayMorning, $saturdayMorning));
    }
}
