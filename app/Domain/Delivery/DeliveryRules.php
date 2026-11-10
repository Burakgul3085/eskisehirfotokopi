<?php

namespace App\Domain\Delivery;

use App\Models\DeliveryDistrict;
use App\Models\DeliveryNeighborhood;
use App\Models\Order;
use App\Support\Settings;
use Carbon\CarbonImmutable;

/**
 * Teslimat kuralları (K6). Hepsi yönetimden gelir: ilçe ücreti (mahalle ücretiyle ezilebilir), ücretsiz teslimat eşiği
 * ve en düşük sipariş (ilçeye özel değer yoksa genel ayar), kesim saati, teslimat yapılmayan günler, saat aralıkları.
 */
class DeliveryRules
{
    public function freeThreshold(?DeliveryDistrict $district = null): ?float
    {
        $value = $district?->free_threshold ?? Settings::get('delivery_free_threshold');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    public function minOrder(?DeliveryDistrict $district = null): ?float
    {
        $value = $district?->min_order ?? Settings::get('delivery_min_order');

        return is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }

    /** Teslimat ücreti: eşik aşıldıysa 0. */
    public function fee(DeliveryDistrict $district, ?string $neighborhood, float $subtotal): float
    {
        $threshold = $this->freeThreshold($district);
        if ($threshold !== null && $subtotal >= $threshold) {
            return 0.0;
        }
        $hood = $neighborhood ? DeliveryNeighborhood::where('district_id', $district->id)->where('name', $neighborhood)->first() : null;

        return (float) ($hood?->fee ?? $district->fee);
    }

    /** Ücretsiz teslimata kalan tutar (eşik yoksa veya aşıldıysa null). */
    public function remainingForFree(float $subtotal, ?DeliveryDistrict $district = null): ?float
    {
        $threshold = $this->freeThreshold($district);

        return $threshold !== null && $subtotal < $threshold ? round($threshold - $subtotal, 2) : null;
    }

    /** @return list<string> */
    public function slots(): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) Settings::get('delivery_slots')))));
    }

    /** @return list<int> ISO gün numaraları (1=Pazartesi) */
    public function closedDays(): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) Settings::get('delivery_closed_days'))), fn ($d) => $d >= 1 && $d <= 7));
    }

    /** Tahmini teslim günü: kesim saatinden önce ve aynı gün teslimat açıksa bugün; kapalı günler atlanır. */
    public function estimate(?DeliveryDistrict $district = null, ?CarbonImmutable $now = null): CarbonImmutable
    {
        $now ??= CarbonImmutable::now();
        [$h, $m] = array_map('intval', explode(':', Settings::get('delivery_cutoff', '15:00')) + [1 => 0]);
        $sameDay = Settings::flag('delivery_same_day') && $now->lt($now->setTime($h, $m));
        $day = $sameDay ? $now->startOfDay() : $now->addDay()->startOfDay();
        $closed = $this->closedDays();
        $extra = (int) ($district?->extra_days ?? 0);

        for ($guard = 0; $guard < 14; $guard++) {
            if (! in_array($day->dayOfWeekIso, $closed, true)) {
                if ($extra === 0) {
                    return $day;
                }
                $extra--;
            }
            $day = $day->addDay();
        }

        return $day;
    }

    /** "Bugün", "Yarın" veya "12 Kasım Çarşamba". */
    public function label(CarbonImmutable $day, ?CarbonImmutable $now = null): string
    {
        $now ??= CarbonImmutable::now();

        return match (true) {
            $day->isSameDay($now) => 'Bugün',
            $day->isSameDay($now->addDay()) => 'Yarın',
            default => $day->locale('tr')->translatedFormat('j F l'),
        };
    }

    /** @return array<int, string> açık ödeme yöntemleri */
    public function paymentMethods(): array
    {
        return array_filter(Order::PAYMENT_METHODS, fn ($label, $key) => Settings::flag('payment_'.$key), ARRAY_FILTER_USE_BOTH);
    }
}
