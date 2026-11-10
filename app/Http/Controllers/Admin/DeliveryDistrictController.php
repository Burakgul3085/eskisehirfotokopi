<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryDistrict;
use App\Support\Activity;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Yönetim › Teslimat bölgeleri (K6). İlçe açılıp kapatılır; ücret, ücretsiz teslimat eşiği, en düşük tutar ve ek gün
 * ilçeye özel girilebilir. Mahalle listesi isteğe bağlıdır: boşsa müşteri mahalleyi kendisi yazar, doluysa yalnız
 * listedekiler seçilebilir. Mahalleye özel ücret "Mahalle|ücret" biçiminde yazılır.
 */
class DeliveryDistrictController extends Controller
{
    public function index(): View
    {
        return view('admin.districts.index', ['districts' => DeliveryDistrict::withCount('neighborhoods')->orderBy('position')->orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.districts.form', ['district' => new DeliveryDistrict(['is_active' => true, 'fee' => 0]), 'hoodText' => '']);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $hoods] = $this->validated($request);
        $district = DB::transaction(function () use ($data, $hoods) {
            $district = DeliveryDistrict::create($data + ['position' => (int) DeliveryDistrict::max('position') + 1]);
            $this->syncHoods($district, $hoods);

            return $district;
        });
        Activity::log('district.created', $district);

        return redirect()->route('admin.districts.index')->with('status', $district->name.' eklendi.');
    }

    public function edit(DeliveryDistrict $district): View
    {
        $hoodText = $district->neighborhoods()->orderBy('name')->get()
            ->map(fn ($n) => $n->name.($n->fee !== null ? '|'.Money::format($n->fee, false) : '').($n->is_active ? '' : '|kapalı'))->implode(PHP_EOL);

        return view('admin.districts.form', ['district' => $district, 'hoodText' => $hoodText]);
    }

    public function update(Request $request, DeliveryDistrict $district): RedirectResponse
    {
        [$data, $hoods] = $this->validated($request, $district);
        DB::transaction(function () use ($district, $data, $hoods) {
            $district->update($data);
            $this->syncHoods($district, $hoods);
        });
        Activity::log('district.updated', $district);

        return redirect()->route('admin.districts.index')->with('status', $district->name.' güncellendi.');
    }

    public function toggle(DeliveryDistrict $district): RedirectResponse
    {
        $district->update(['is_active' => ! $district->is_active]);
        Activity::log('district.updated', $district, ['is_active' => $district->is_active]);

        return back()->with('status', $district->name.($district->is_active ? ' teslimata açıldı.' : ' teslimata kapatıldı.'));
    }

    public function destroy(DeliveryDistrict $district): RedirectResponse
    {
        $district->delete(); // siparişlerde ilçe adı ayrıca saklandığı için geçmiş bozulmaz
        Activity::log('district.deleted', null, ['name' => $district->name]);

        return redirect()->route('admin.districts.index')->with('status', $district->name.' silindi.');
    }

    /** @return array{0: array, 1: list<array{name: string, fee: ?string, is_active: bool}>} */
    private function validated(Request $request, ?DeliveryDistrict $district = null): array
    {
        foreach (['fee', 'free_threshold', 'min_order'] as $f) {
            if (filled($request->input($f))) {
                $request->merge([$f => Money::parse($request->input($f)) ?? $request->input($f)]);
            }
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('delivery_districts', 'name')->ignore($district?->id)],
            'fee' => ['required', 'numeric', 'min:0', 'max:10000'],
            'free_threshold' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'extra_days' => ['required', 'integer', 'min:0', 'max:14'],
            'is_active' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:200'],
            'neighborhoods' => ['nullable', 'string', 'max:20000'],
        ]);

        $hoods = [];
        foreach (preg_split('/\R/u', (string) ($data['neighborhoods'] ?? '')) as $i => $line) {
            $parts = array_map('trim', explode('|', $line));
            if ($parts[0] === '') {
                continue;
            }
            $fee = null;
            $active = true;
            foreach (array_slice($parts, 1) as $p) {
                if (mb_strtolower($p) === 'kapalı' || mb_strtolower($p) === 'kapali') {
                    $active = false;
                } elseif ($p !== '') {
                    $fee = Money::parse($p);
                    if ($fee === null) {
                        throw ValidationException::withMessages(['neighborhoods' => ($i + 1).'. satırdaki ücret anlaşılamadı: '.$p]);
                    }
                }
            }
            $hoods[mb_strtolower($parts[0])] = ['name' => mb_substr($parts[0], 0, 120), 'fee' => $fee, 'is_active' => $active];
        }
        unset($data['neighborhoods']);

        return [$data, array_values($hoods)];
    }

    private function syncHoods(DeliveryDistrict $district, array $hoods): void
    {
        $district->neighborhoods()->delete();
        foreach (array_chunk($hoods, 500) as $chunk) {
            $district->neighborhoods()->createMany($chunk);
        }
    }
}
