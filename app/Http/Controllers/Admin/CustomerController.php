<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessageThread;
use App\Models\Order;
use App\Models\User;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Yönetim › Müşteriler: üyeler, sipariş sayısı ve toplamı, ayrıntı (siparişler, adresler, mesajlar), hesabı kapatma. */
class CustomerController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $digits = preg_replace('/\D/', '', $term);
        $sort = $request->query('sirala', 'yeni');

        $customers = User::query()
            ->withCount(['orders' => fn ($q) => $q->where('status', '!=', 'cancelled')])
            ->withSum(['orders as orders_total' => fn ($q) => $q->where('status', '!=', 'cancelled')], 'total')
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($term, $digits) {
                $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%");
                if (strlen($digits) >= 4) {
                    $w->orWhere('phone', 'like', "%{$digits}%");
                }
            }))
            ->when($request->query('durum') === 'kapali', fn ($q) => $q->where('is_active', false))
            ->when($sort === 'siparis', fn ($q) => $q->orderByDesc('orders_count'), fn ($q) => $sort === 'tutar' ? $q->orderByDesc('orders_total') : $q->latest())
            ->paginate(30)->withQueryString();

        return view('admin.customers.index', [
            'customers' => $customers,
            'guestOrders' => Order::whereNull('user_id')->count(),
        ]);
    }

    public function show(User $customer): View
    {
        return view('admin.customers.show', [
            'customer' => $customer->load('addresses.district'),
            'orders' => $customer->orders()->withCount('items')->latest()->limit(50)->get(),
            'threads' => MessageThread::where('user_id', $customer->id)->orWhere('email', $customer->email)->latest('last_message_at')->limit(20)->get(),
            'stats' => $customer->orders()->where('status', '!=', 'cancelled')->selectRaw('count(*) as c, coalesce(sum(total),0) as t')->first(),
        ]);
    }

    public function toggle(User $customer): RedirectResponse
    {
        $customer->is_active = ! $customer->is_active;
        if (! $customer->is_active) {
            // Açık oturumlar kapanır, "beni hatırla" çerezleri geçersizleşir.
            $customer->setRememberToken(Str::random(60));
            DB::table('sessions')->where('user_id', $customer->id)->delete();
        }
        $customer->save();
        Activity::log('customer.toggled', $customer, ['is_active' => $customer->is_active]);

        return back()->with('status', $customer->is_active ? 'Hesap yeniden açıldı.' : 'Hesap kapatıldı; müşteri giriş yapamaz.');
    }
}
