<?php

namespace App\Http\Controllers;

use App\Rules\TurkishPhone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Hesabım: özet ve profil. Siparişler, adresler ve favoriler sonraki aşamalarda eklenir. */
class AccountController extends Controller
{
    public function index(Request $request): View
    {
        return view('account.index', ['user' => $request->user()]);
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
