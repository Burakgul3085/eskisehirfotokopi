<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Support\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Yönetici hesapları (yalnızca süper yönetici). Son etkin süper yönetici pasifleştirilemez, silinemez, rolü düşürülemez. */
class AdminUserController extends Controller
{
    public function index(): View
    {
        return view('admin.admins.index', ['admins' => Admin::orderByDesc('is_active')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.admins.form', ['admin' => new Admin(['role' => 'staff', 'is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $admin = Admin::create($data);
        Activity::log('admin.created', $admin, ['email' => $admin->email, 'role' => $admin->role]);

        return redirect()->route('admin.admins.index')->with('status', 'Yönetici eklendi.');
    }

    public function edit(Admin $admin): View
    {
        return view('admin.admins.form', ['admin' => $admin]);
    }

    public function update(Request $request, Admin $admin): RedirectResponse
    {
        $data = $this->validated($request, $admin);
        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }
        if ($this->isLastSuper($admin) && ($data['role'] !== 'super' || ! $data['is_active'])) {
            return back()->withErrors(['role' => 'Son etkin süper yöneticinin rolü değiştirilemez veya pasifleştirilemez.']);
        }
        $admin->update($data);
        Activity::log('admin.updated', $admin, collect($admin->getChanges())->except('updated_at')->all());

        return redirect()->route('admin.admins.index')->with('status', 'Yönetici güncellendi.');
    }

    public function destroy(Request $request, Admin $admin): RedirectResponse
    {
        if ($admin->is($request->user('admin'))) {
            return back()->withErrors(['admin' => 'Kendi hesabınızı silemezsiniz.']);
        }
        if ($this->isLastSuper($admin)) {
            return back()->withErrors(['admin' => 'Son etkin süper yönetici silinemez.']);
        }
        Activity::log('admin.deleted', null, ['email' => $admin->email]);
        $admin->delete();

        return redirect()->route('admin.admins.index')->with('status', 'Yönetici silindi.');
    }

    private function validated(Request $request, ?Admin $admin): array
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', Rule::unique('admins', 'email')->ignore($admin)],
            'role' => ['required', Rule::in(array_keys(Admin::ROLES))],
            'password' => [$admin ? 'nullable' : 'required', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }

    private function isLastSuper(Admin $admin): bool
    {
        return $admin->isSuper() && $admin->is_active && Admin::where('role', 'super')->where('is_active', true)->count() === 1;
    }
}
