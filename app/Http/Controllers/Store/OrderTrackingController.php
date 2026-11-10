<?php

namespace App\Http\Controllers\Store;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Rules\TurkishPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Üyeliksiz sipariş takibi (K8): sipariş no + telefon, veya e-postadaki gizli bağlantı. */
class OrderTrackingController extends Controller
{
    public function form(): View
    {
        return view('store.track');
    }

    public function lookup(Request $request): RedirectResponse
    {
        $data = $request->validate(['number' => ['required', 'string', 'max:20'], 'phone' => ['required', 'string', 'max:20']]);
        $order = Order::where('number', strtoupper(trim($data['number'])))->first();
        $phone = TurkishPhone::normalize($data['phone']);

        if (! $order || ! $phone || ! hash_equals($order->phone, $phone)) {
            return back()->withInput()->withErrors(['number' => 'Bu sipariş numarası ve telefonla eşleşen sipariş bulunamadı.']);
        }

        return redirect()->to($order->trackingUrl());
    }

    public function show(Order $order, string $token): View
    {
        abort_unless(hash_equals($order->tracking_token, $token), 404);

        return view('store.order', ['order' => $order->load('items', 'history'), 'context' => 'track']);
    }
}
