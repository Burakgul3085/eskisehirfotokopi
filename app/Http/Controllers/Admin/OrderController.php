<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Orders\OrderStatusService;
use App\Http\Controllers\Controller;
use App\Models\DeliveryDistrict;
use App\Models\Order;
use App\Support\Activity;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Yönetim › Siparişler: liste ve filtreler, ayrıntı, durum değişikliği, yazdırılabilir fiş, günlük teslimat listesi. */
class OrderController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('durum');
        $query = Order::query()->withCount('items')->search($request->query('q'))
            ->when(isset(Order::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($status === 'acik', fn ($q) => $q->whereNotIn('status', ['delivered', 'cancelled']))
            ->when($request->integer('ilce'), fn ($q, $id) => $q->where('district_id', $id))
            ->when($request->query('odeme'), fn ($q, $m) => $q->where('payment_method', $m))
            ->when($request->date('baslangic'), fn ($q, $d) => $q->where('created_at', '>=', $d->startOfDay()))
            ->when($request->date('bitis'), fn ($q, $d) => $q->where('created_at', '<=', $d->endOfDay()));

        return view('admin.orders.index', [
            'orders' => $query->latest()->paginate(30)->withQueryString(),
            'counts' => Order::query()->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status'),
            'districts' => DeliveryDistrict::orderBy('position')->get(['id', 'name']),
        ]);
    }

    public function show(Order $order): View
    {
        return view('admin.orders.show', ['order' => $order->load('items.product', 'history.admin', 'user')]);
    }

    public function updateStatus(Request $request, Order $order, OrderStatusService $service): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Order::STATUSES))],
            'note' => ['nullable', 'string', 'max:500'],
            'notify' => ['nullable', 'boolean'],
        ]);
        if ($data['status'] === 'cancelled' && blank($data['note'] ?? null)) {
            return back()->withErrors(['note' => 'İptal nedeni yazın; müşteriye bildirilir.']);
        }
        try {
            $service->change($order, $data['status'], $data['note'] ?? null, $request->user('admin'), $request->boolean('notify'));
        } catch (\DomainException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return back()->with('status', 'Sipariş durumu güncellendi: '.$order->shortStatus().'.');
    }

    public function updateNote(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['admin_note' => ['nullable', 'string', 'max:2000']]);
        $order->update(['admin_note' => $data['admin_note']]);
        Activity::log('order.note', $order);

        return back()->with('status', 'İç not kaydedildi.');
    }

    /** Teslimat fişi (yazdırılabilir). */
    public function slip(Order $order): View
    {
        return view('admin.orders.slip', ['orders' => collect([$order->load('items')])]);
    }

    /** Günlük teslimat listesi: seçilen günün açık siparişleri ilçe/mahalleye göre gruplu, yazdırılabilir. */
    public function deliveries(Request $request): View
    {
        $day = $request->date('gun') ? CarbonImmutable::parse($request->date('gun')) : CarbonImmutable::today();
        $orders = Order::with('items')->whereDate('delivery_date', $day)->whereNotIn('status', ['delivered', 'cancelled'])
            ->orderBy('district_name')->orderBy('neighborhood')->orderBy('delivery_slot')->get();

        if ($request->boolean('yazdir')) {
            return view('admin.orders.slip', ['orders' => $orders, 'day' => $day]);
        }

        return view('admin.orders.deliveries', ['orders' => $orders, 'day' => $day]);
    }
}
