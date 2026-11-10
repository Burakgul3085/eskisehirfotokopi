<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\DeliveryDistrict;
use App\Rules\TurkishPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Hesabım › Adreslerim. Yalnız teslimat yapılan ilçeler seçilebilir. */
class AddressController extends Controller
{
    public const MAX = 10;

    public function index(Request $request): View
    {
        return view('account.addresses.index', ['addresses' => $request->user()->addresses()->with('district')->get()]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user()->addresses()->count() >= self::MAX) {
            return redirect()->route('account.addresses.index')->withErrors(['cart' => 'En fazla '.self::MAX.' adres kaydedebilirsiniz.']);
        }

        return $this->form(new Address(['name' => $request->user()->name, 'phone' => $request->user()->phone]));
    }

    public function store(Request $request): RedirectResponse
    {
        abort_if($request->user()->addresses()->count() >= self::MAX, 422);
        $data = $this->validated($request);
        $address = $request->user()->addresses()->create($data + ['is_default' => ! $request->user()->addresses()->exists()]);
        $this->applyDefault($request, $address);

        return redirect()->route('account.addresses.index')->with('status', 'Adres kaydedildi.');
    }

    public function edit(Request $request, Address $address): View
    {
        $this->authorizeOwner($request, $address);

        return $this->form($address);
    }

    public function update(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $address->update($this->validated($request));
        $this->applyDefault($request, $address);

        return redirect()->route('account.addresses.index')->with('status', 'Adres güncellendi.');
    }

    public function destroy(Request $request, Address $address): RedirectResponse
    {
        $this->authorizeOwner($request, $address);
        $wasDefault = $address->is_default;
        $address->delete();
        if ($wasDefault) {
            $request->user()->addresses()->oldest()->first()?->update(['is_default' => true]);
        }

        return redirect()->route('account.addresses.index')->with('status', 'Adres silindi.');
    }

    private function form(Address $address): View
    {
        return view('account.addresses.form', [
            'address' => $address,
            'districts' => DeliveryDistrict::active()->with(['neighborhoods' => fn ($q) => $q->where('is_active', true)])->get(),
        ]);
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', new TurkishPhone],
            'district_id' => ['required', Rule::exists('delivery_districts', 'id')->where('is_active', true)],
            'neighborhood' => ['required', 'string', 'max:120'],
            'address' => ['required', 'string', 'min:10', 'max:500'],
        ]);
        $data['phone'] = TurkishPhone::normalize($data['phone']);

        return $data;
    }

    private function applyDefault(Request $request, Address $address): void
    {
        if ($request->boolean('is_default')) {
            $request->user()->addresses()->whereKeyNot($address->id)->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        }
    }

    private function authorizeOwner(Request $request, Address $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
