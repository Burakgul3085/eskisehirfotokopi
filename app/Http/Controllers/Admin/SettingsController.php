<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\SiteMail;
use App\Support\Activity;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/** Site ayarları, gruplar halinde (genel, iletişim, görünüm, SEO, e-posta). */
class SettingsController extends Controller
{
    public const GROUPS = [
        'genel' => 'Genel',
        'teslimat' => 'Teslimat ve sipariş',
        'iletisim' => 'İletişim ve sosyal medya',
        'gorunum' => 'Görünüm',
        'seo' => 'SEO',
        'eposta' => 'E-posta',
    ];

    public function edit(string $group): View
    {
        abort_unless(isset(self::GROUPS[$group]), 404);

        return view('admin.settings.'.$group, ['group' => $group, 'groups' => self::GROUPS, 's' => Settings::all()]);
    }

    public function update(Request $request, string $group): RedirectResponse
    {
        abort_unless(isset(self::GROUPS[$group]), 404);

        $values = match ($group) {
            'genel' => $request->validate([
                'site_name' => ['required', 'string', 'max:80'],
                'site_tagline' => ['nullable', 'string', 'max:160'],
                'announcement' => ['nullable', 'string', 'max:200'],
                'announcement_link' => ['nullable', 'url', 'max:255'],
                'store_open' => ['required', 'in:0,1'],
                'closed_message' => ['nullable', 'string', 'max:300'],
                'footer_about' => ['nullable', 'string', 'max:600'],
                'header_links' => ['nullable', 'string', 'max:1000'],
                'trust_items' => ['nullable', 'string', 'max:1000'],
                'copyright' => ['nullable', 'string', 'max:160'],
            ]),
            'iletisim' => $request->validate([
                'phone' => ['nullable', 'string', 'max:30'],
                'whatsapp' => ['nullable', 'regex:/^[0-9 +()-]{10,20}$/'],
                'email' => ['nullable', 'email', 'max:190'],
                'address' => ['nullable', 'string', 'max:300'],
                'working_hours' => ['nullable', 'string', 'max:300'],
                'maps_url' => ['nullable', 'url', 'max:500'],
                'contact_intro' => ['nullable', 'string', 'max:500'],
                'contact_consent' => ['nullable', 'string', 'max:300'],
                'instagram' => ['nullable', 'url', 'max:255'],
                'facebook' => ['nullable', 'url', 'max:255'],
                'x' => ['nullable', 'url', 'max:255'],
                'youtube' => ['nullable', 'url', 'max:255'],
                'tiktok' => ['nullable', 'url', 'max:255'],
            ]),
            'teslimat' => $this->delivery($request),
            'gorunum' => $this->appearance($request),
            'seo' => $request->validate([
                'meta_title' => ['nullable', 'string', 'max:70'],
                'meta_description' => ['nullable', 'string', 'max:170'],
            ]),
            'eposta' => $this->mail($request),
        };

        Settings::put($values);
        Activity::log('settings.updated', null, ['group' => $group, 'keys' => array_keys($values)]);

        return redirect()->route('admin.settings.edit', $group)->with('status', 'Ayarlar kaydedildi.');
    }

    /** Kayıtlı SMTP ayarlarıyla hemen (kuyruğa koymadan) deneme e-postası gönderir; hata ekranda görünür. */
    public function testMail(Request $request): RedirectResponse
    {
        $data = $request->validate(['to' => ['required', 'email', 'max:190']]);
        try {
            Mail::to($data['to'])->sendNow(SiteMail::test());
        } catch (Throwable $e) {
            return back()->withInput()->withErrors(['to' => 'Gönderilemedi: '.mb_substr($e->getMessage(), 0, 300)]);
        }
        Activity::log('settings.test_mail', null, ['to' => $data['to']]);

        return back()->with('status', 'Deneme e-postası '.$data['to'].' adresine gönderildi.');
    }

    private function delivery(Request $request): array
    {
        foreach (['delivery_free_threshold', 'delivery_min_order'] as $f) {
            if (filled($request->input($f))) {
                $request->merge([$f => Money::parse($request->input($f)) ?? $request->input($f)]);
            }
        }
        $data = $request->validate([
            'orders_open' => ['required', 'in:0,1'],
            'orders_closed_message' => ['nullable', 'string', 'max:300'],
            'delivery_free_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'delivery_min_order' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'delivery_cutoff' => ['required', 'date_format:H:i'],
            'delivery_same_day' => ['required', 'in:0,1'],
            'delivery_closed_days' => ['nullable', 'array'],
            'delivery_closed_days.*' => ['integer', 'between:1,7'],
            'delivery_slots' => ['nullable', 'string', 'max:500'],
            'delivery_info' => ['nullable', 'string', 'max:500'],
            'payment_cash' => ['nullable', 'boolean'],
            'payment_card' => ['nullable', 'boolean'],
        ]);
        if (! $request->boolean('payment_cash') && ! $request->boolean('payment_card')) {
            throw ValidationException::withMessages(['payment_cash' => 'En az bir ödeme yöntemi açık olmalı.']);
        }
        $data['delivery_closed_days'] = implode(',', array_unique(array_map('intval', $data['delivery_closed_days'] ?? [])));
        $data['payment_cash'] = $request->boolean('payment_cash') ? '1' : '0';
        $data['payment_card'] = $request->boolean('payment_card') ? '1' : '0';
        foreach (['delivery_free_threshold', 'delivery_min_order'] as $f) {
            $data[$f] = filled($data[$f] ?? null) ? (string) $data[$f] : null;
        }

        return $data;
    }

    private function appearance(Request $request): array
    {
        $data = $request->validate([
            'color_primary' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'color_accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'favicon' => ['nullable', 'image', 'mimes:png,ico,svg', 'max:512'],
            'remove_logo' => ['nullable', 'boolean'],
            'remove_favicon' => ['nullable', 'boolean'],
        ]);
        $values = ['color_primary' => strtolower($data['color_primary']), 'color_accent' => strtolower($data['color_accent'])];

        foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $field => $key) {
            $old = Settings::get($key);
            if ($request->hasFile($field)) {
                $values[$key] = $request->file($field)->store('site', 'public');
            } elseif ($request->boolean('remove_'.$field)) {
                $values[$key] = null;
            } else {
                continue;
            }
            if ($old) {
                Storage::disk('public')->delete($old);
            }
        }

        return $values;
    }

    private function mail(Request $request): array
    {
        $data = $request->validate([
            'mail_host' => ['nullable', 'string', 'max:190'],
            'mail_port' => ['nullable', 'integer', 'between:1,65535'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,none'],
            'mail_username' => ['nullable', 'string', 'max:190'],
            'mail_password' => ['nullable', 'string', 'max:255'],
            'mail_from_address' => ['nullable', 'email', 'max:190'],
            'mail_from_name' => ['nullable', 'string', 'max:80'],
            'notify_emails' => ['nullable', 'string', 'max:500'],
        ]);
        // Parola alanı boş bırakılırsa mevcut parola korunur.
        if (filled($data['mail_password'] ?? null)) {
            Settings::putSecret('mail_password', $data['mail_password']);
        }
        unset($data['mail_password']);
        $emails = collect(preg_split('/[\s,;]+/', (string) ($data['notify_emails'] ?? '')))->filter()->unique();
        if ($emails->contains(fn ($e) => ! filter_var($e, FILTER_VALIDATE_EMAIL))) {
            throw ValidationException::withMessages(['notify_emails' => 'Bildirim adreslerinden biri geçersiz.']);
        }
        $data['notify_emails'] = $emails->implode(', ');

        return $data;
    }
}
