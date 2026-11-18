<?php

namespace App\Http\Controllers;

use App\Rules\TurkishPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Hesabım: genel bakış paneli, bilgiler ve parola. */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $open = fn () => $user->orders()->whereNotIn('status', ['delivered', 'cancelled']);

        return view('account.index', [
            'user' => $user,
            'stats' => [
                'orders' => $user->orders()->count(),
                'open' => $open()->count(),
                'favorites' => $user->favorites()->visible()->count(),
                'addresses' => $user->addresses()->count(),
            ],
            // Devam eden siparişler adım çizelgesiyle en üstte; altında son siparişler
            'active' => $open()->with('items')->limit(3)->get(),
            'recent' => $user->orders()->withCount('items')->limit(5)->get(),
            'favorites' => $user->favorites()->visible()->with('publisher:id,name,slug')->limit(5)->get(),
        ]);
    }

    public function settings(Request $request): View
    {
        return view('account.settings', ['user' => $request->user()]);
    }

    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email,'.$user->id],
            'phone' => ['required', new TurkishPhone],
        ]);
        $user->update(['name' => trim($data['name']), 'email' => $data['email'], 'phone' => TurkishPhone::normalize($data['phone'])]);

        return back()->with('status', 'Bilgileriniz güncellendi.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);
        if (! Hash::check($data['current_password'], $request->user()->password)) {
            return back()->withErrors(['current_password' => 'Mevcut parola hatalı.']);
        }
        $request->user()->update(['password' => $data['password']]);

        return back()->with('status', 'Parolanız değiştirildi.');
    }
}
