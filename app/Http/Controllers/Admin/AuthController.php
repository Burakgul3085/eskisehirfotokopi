<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\Activity;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Yönetici girişi. Art arda Admin::MAX_FAILED_LOGINS hatalı denemede hesap Admin::LOCK_MINUTES dakika kilitlenir;
 * ayrıca IP başına istek sınırı vardır (routes/admin.php, throttle:admin-login).
 */
class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        return Auth::guard('admin')->check() ? redirect()->route('admin.dashboard') : view('admin.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $fail = fn (string $msg = 'E-posta veya parola hatalı.') => back()->withInput($request->only('email'))->withErrors(['email' => $msg]);

        $admin = Admin::where('email', mb_strtolower($data['email']))->first();
        if (! $admin) {
            Hash::check($data['password'], '$2y$12$'.str_repeat('a', 53)); // süre farkından hesap varlığı anlaşılmasın

            return $fail();
        }
        if ($admin->isLocked()) {
            return $fail('Çok sayıda hatalı deneme. Hesap '.$admin->locked_until->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE).' sonra açılacak.');
        }
        if (! Hash::check($data['password'], $admin->password)) {
            $failed = $admin->failed_logins + 1;
            $admin->forceFill($failed >= Admin::MAX_FAILED_LOGINS
                ? ['failed_logins' => 0, 'locked_until' => now()->addMinutes(Admin::LOCK_MINUTES)]
                : ['failed_logins' => $failed])->save();

            return $fail();
        }
        if (! $admin->is_active) {
            return $fail('Bu yönetici hesabı pasif.');
        }

        Auth::guard('admin')->login($admin, $request->boolean('remember'));
        $request->session()->regenerate();
        $admin->forceFill(['failed_logins' => 0, 'locked_until' => null, 'last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        Activity::log('admin.login');

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
