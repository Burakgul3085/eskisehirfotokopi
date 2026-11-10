<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\TurkishPhone;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/** Müşteri girişi, üyelik, çıkış ve parola sıfırlama. Üyeliksiz sipariş de mümkündür (K8). */
class CustomerAuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', mb_strtolower($data['email']))->first();
        if (! $user || ! $user->is_active || ! Auth::guard('web')->attempt(['email' => $user->email, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withInput($request->only('email'))->withErrors(['email' => 'E-posta veya parola hatalı.']);
        }

        $request->session()->regenerate();
        $user->forceFill(['last_login_at' => now()])->save();

        return redirect()->intended(route('account.index'));
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'phone' => ['required', new TurkishPhone],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
            'terms' => ['accepted'],
        ], [
            'email.unique' => 'Bu e-posta ile zaten bir hesap var. Giriş yapabilir veya parolanızı sıfırlayabilirsiniz.',
            'terms.accepted' => 'Üyelik koşullarını ve KVKK aydınlatma metnini onaylamanız gerekir.',
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => $data['email'],
            'phone' => TurkishPhone::normalize($data['phone']),
            'password' => $data['password'],
            'last_login_at' => now(),
        ]);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route('account.index')->with('status', 'Hoş geldiniz! Hesabınız oluşturuldu.');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function showForgot(): View
    {
        return view('auth.forgot-password');
    }

    public function sendResetLink(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);
        Password::broker('users')->sendResetLink(['email' => mb_strtolower($request->input('email'))]);

        // Hesabın var olup olmadığı açığa çıkarılmaz.
        return back()->with('status', 'Bu e-postaya kayıtlı bir hesap varsa parola sıfırlama bağlantısı gönderildi.');
    }

    public function showReset(Request $request, string $token): View
    {
        return view('auth.reset-password', ['token' => $token, 'email' => $request->query('email')]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(8)->letters()->numbers()],
        ]);

        $status = Password::broker('users')->reset(
            ['email' => mb_strtolower($data['email'])] + $data,
            function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', 'Parolanız değiştirildi. Yeni parolanızla giriş yapabilirsiniz.')
            : back()->withInput($request->only('email'))->withErrors(['email' => 'Bağlantı geçersiz veya süresi dolmuş. Yeniden isteyin.']);
    }
}
