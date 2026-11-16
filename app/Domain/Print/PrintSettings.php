<?php

namespace App\Domain\Print;

use App\Support\Activity;
use App\Support\Money;
use App\Support\Settings;
use Illuminate\Validation\ValidationException;

/**
 * Fotokopi ayarları (Yönetim › Fotokopi): açık/kapalı, yaprak başı fiyat kademeleri, A3 çarpanı, spiral fiyatı, sınırlar
 * ve saklama süreleri. Settings "print" anahtarında JSON olarak tutulur (B2B'den uyarlandı; KDV ve fatura yok).
 *
 * Kademe kopya başı yaprak sayısına göre seçilir (önlü arkalı yaprağı yarıya indirir; kopya kademeyi değiştirmez); her kademede siyah-beyaz
 * ve renkli yaprak fiyatı vardır. Sayfa sınırı yoktur: her belgenin fiyatı hesaplanır.
 */
class PrintSettings
{
    public const DEFAULTS = [
        'enabled' => true,
        'bw_enabled' => true,
        'color_enabled' => true,
        'tiers' => [
            ['from' => 1, 'bw' => '2.00', 'color' => '6.00'],
            ['from' => 100, 'bw' => '1.50', 'color' => '5.00'],
            ['from' => 200, 'bw' => '1.00', 'color' => '4.00'],
        ],
        'a3_enabled' => false,
        'a3_multiplier' => '2.00',
        'spiral_price' => '40.00',
        'max_mb' => 200,
        'max_copies' => 100,
        'unordered_days' => 7,
        'ordered_days' => 30,
        'intro' => 'PDF, Word, PowerPoint, Excel veya fotoğraf yükleyin; sayfa sayısı otomatik çıkar, fiyatı anında görün. Basıp kapınıza getirelim.',
        'prep_text' => 'Baskılar aynı gün hazırlanır, teslimat gününüzde kapınıza gelir.',
        // Sayfa metinleri (Yönetim › Fotokopi › Sayfa metinleri); boş bırakılan başlık varsayılana döner, diğerleri gizlenir
        'page_kicker' => 'Çevrimiçi fotokopi',
        'page_title' => 'Belgeni yükle,',
        'page_title_accent' => 'kapına getirelim.',
        'steps' => "upload|Belgeni yükle|Sayfa sayısı otomatik çıkar.\nsettings|Baskıyı seç|Renk, düzen, cilt ve kopya.\ncart|Sepete ekle|Kitaplarınla aynı siparişte gelsin.",
        'upload_title' => 'Belgelerini yükle',
        'upload_subtitle' => 'Birden fazla belge seçebilirsin; her birinin baskı ayarı ayrıdır.',
        'consent_text' => 'Yüklediğim belgeleri çoğaltma hakkım olduğunu, telif hakkıyla korunan eserlerde yasal sınırlar içinde kaldığımı onaylıyorum.',
        'consent_hint' => 'Yüklemeye başlamak için yukarıdaki onay kutusunu işaretle.',
        'drop_title' => 'Dosyaları buraya sürükle',
        'drop_pick' => 'bilgisayarından seç',
        'tip_text' => 'İpucu: Sayfa düzeninin aynen korunması için belgeyi mümkünse PDF olarak yükle. Word ve PowerPoint dosyaları PDF’e çevrilerek basılır; yazı tipleri farklı görünebilir.',
        'price_title' => 'Fiyat listesi',
        'price_subtitle' => 'Yaprak başı fiyat; çok basana daha ucuz.',
        'price_badge' => 'yaprak başı, en düşük fiyat',
        'price_best_label' => 'En uygun',
    ];

    /** Sayfa metni alanları ve en fazla uzunlukları */
    public const TEXTS = ['page_kicker' => 60, 'page_title' => 80, 'page_title_accent' => 80, 'steps' => 600, 'upload_title' => 80,
        'upload_subtitle' => 200, 'consent_text' => 300, 'consent_hint' => 200, 'drop_title' => 80, 'drop_pick' => 60, 'tip_text' => 400,
        'price_title' => 60, 'price_subtitle' => 200, 'price_badge' => 80, 'price_best_label' => 40];

    /** İstek boyunca tekrar tekrar çözümlenmesin. */
    private ?array $cache = null;

    public function all(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }
        $saved = json_decode((string) Settings::get('print'), true);
        $all = array_replace(self::DEFAULTS, is_array($saved) ? $saved : []);
        $all['tiers'] = array_map(fn (array $t) => ['from' => (int) $t['from'], 'bw' => (string) $t['bw'], 'color' => (string) $t['color']], $all['tiers']);

        return $this->cache = $all;
    }

    /** Sayfa metni; başlıklar boşsa varsayılan, diğerleri boşsa '' (gösterilmez). */
    public function text(string $key): string
    {
        $value = trim((string) ($this->all()[$key] ?? ''));
        $required = ['page_title', 'upload_title', 'drop_title', 'drop_pick', 'price_title', 'consent_text'];

        return $value === '' && in_array($key, $required, true) ? self::DEFAULTS[$key] : $value;
    }

    /** Adım şeridi: "simge|Başlık|Açıklama" satırları. @return list<array{icon: string, title: string, text: string}> */
    public function steps(): array
    {
        $out = [];
        foreach (preg_split('/\R/', $this->text('steps')) as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) >= 2 && $parts[1] !== '') {
                $out[] = ['icon' => $parts[0] ?: 'check', 'title' => $parts[1], 'text' => $parts[2] ?? ''];
            }
        }

        return array_slice($out, 0, 4);
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function enabled(): bool
    {
        return (bool) $this->get('enabled');
    }

    /** @return list<string> seçilebilen renkler ('bw', 'color') */
    public function colors(): array
    {
        $all = $this->all();

        return array_values(array_filter(array_keys(PrintOptions::COLORS), fn (string $c) => (bool) $all[$c.'_enabled']));
    }

    public function colorEnabled(string $color): bool
    {
        return in_array($color, $this->colors(), true);
    }

    public function a3Enabled(): bool
    {
        return (bool) $this->get('a3_enabled');
    }

    public function maxCopies(): int
    {
        return (int) $this->get('max_copies');
    }

    public function maxBytes(): int
    {
        return (int) $this->get('max_mb') * 1024 * 1024;
    }

    /**
     * Kaydı engellemeyen fiyat uyarıları: üst kademede yaprak fiyatı alt kademeden yüksekse.
     *
     * @return list<string>
     */
    public function warnings(): array
    {
        $warnings = [];
        $tiers = collect($this->get('tiers'))->sortBy('from')->values();
        foreach ($tiers->skip(1) as $i => $tier) {
            foreach (PrintOptions::COLORS as $key => $label) {
                if (bccomp($tier[$key], $tiers[$i - 1][$key], 2) > 0) {
                    $warnings[] = "{$label}: {$tier['from']}. yapraktan başlayan kademenin yaprak fiyatı (".Money::format($tier[$key]).') bir önceki kademeden ('.Money::format($tiers[$i - 1][$key]).') yüksek.';
                }
            }
        }

        return $warnings;
    }

    /**
     * Yönetim formunu doğrulayıp kaydeder.
     *
     * @throws ValidationException
     */
    public function save(array $input): void
    {
        $errors = [];
        $money = function (mixed $value, string $key) use (&$errors): string {
            $parsed = Money::parse($value);
            if ($parsed === null) {
                $errors[$key] = 'Geçerli bir tutar girin (ör. 1,50).';
            }

            return (string) $parsed;
        };
        $int = function (mixed $value, string $key, int $min, int $max) use (&$errors): int {
            $n = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
            if ($n === false) {
                $errors[$key] = "{$min} ile {$max} arasında bir sayı girin.";
            }

            return (int) $n;
        };

        $tiers = [];
        foreach (array_values((array) ($input['tiers'] ?? [])) as $i => $row) {
            if (blank($row['from'] ?? null)) {
                continue;
            }
            $tier = ['from' => $int($row['from'], "tiers.{$i}.from", 1, 1000000)];
            foreach (['bw', 'color'] as $k) {
                $tier[$k] = $money($row[$k] ?? null, "tiers.{$i}.{$k}");
            }
            $tiers[] = $tier;
        }
        usort($tiers, fn ($a, $b) => $a['from'] <=> $b['from']);
        if (! $tiers || $tiers[0]['from'] !== 1) {
            $errors['tiers'] = 'İlk fiyat kademesi 1. yapraktan başlamalıdır.';
        }
        if (count(array_unique(array_column($tiers, 'from'))) !== count($tiers)) {
            $errors['tiers'] = 'Her kademenin başlangıcı farklı olmalıdır.';
        }

        // Renkler hızlı düğmelerle (toggleColor) yönetilir; formda yoksa mevcut değer korunur.
        $bw = array_key_exists('bw_enabled', $input) ? filter_var($input['bw_enabled'], FILTER_VALIDATE_BOOL) : (bool) $this->get('bw_enabled');
        $color = array_key_exists('color_enabled', $input) ? filter_var($input['color_enabled'], FILTER_VALIDATE_BOOL) : (bool) $this->get('color_enabled');
        if (! $bw && ! $color) {
            $errors['color_enabled'] = 'Siyah-beyaz ve renkliden en az biri açık olmalıdır. Fotokopiyi tamamen kapatmak için "Fotokopi siparişi" seçeneğini kapatın.';
        }

        $data = [
            'enabled' => filter_var($input['enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'bw_enabled' => $bw,
            'color_enabled' => $color,
            'tiers' => $tiers,
            'a3_enabled' => filter_var($input['a3_enabled'] ?? false, FILTER_VALIDATE_BOOL),
            'a3_multiplier' => $money($input['a3_multiplier'] ?? '2', 'a3_multiplier'),
            'spiral_price' => $money($input['spiral_price'] ?? null, 'spiral_price'),
            'max_mb' => $int($input['max_mb'] ?? 200, 'max_mb', 1, 500),
            'max_copies' => $int($input['max_copies'] ?? 100, 'max_copies', 1, 1000),
            'unordered_days' => $int($input['unordered_days'] ?? 7, 'unordered_days', 1, 90),
            'ordered_days' => $int($input['ordered_days'] ?? 30, 'ordered_days', 1, 365),
            'intro' => mb_substr(trim((string) ($input['intro'] ?? '')), 0, 400),
            'prep_text' => mb_substr(trim((string) ($input['prep_text'] ?? '')), 0, 200),
        ];
        // Sayfa metinleri: formda yoksa mevcut değer korunur
        foreach (self::TEXTS as $key => $max) {
            $data[$key] = array_key_exists($key, $input)
                ? mb_substr(trim(str_replace("\r\n", "\n", (string) $input[$key])), 0, $max)
                : $this->get($key);
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        Settings::put(['print' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
        $this->cache = null;
        Activity::log('print.settings', null, ['enabled' => $data['enabled'], 'bw_enabled' => $bw, 'color_enabled' => $color]);
    }

    /**
     * Siyah-beyaz veya renkli baskıyı tek tıkla açar/kapatır (ör. renkli yazıcı arızası). En az biri açık kalmalıdır.
     *
     * @return bool yeni durum
     *
     * @throws ValidationException son açık renk kapatılmak istenirse
     */
    public function toggleColor(string $color): bool
    {
        $key = $color.'_enabled';
        $all = $this->all();
        $new = ! $all[$key];
        $other = $color === 'bw' ? 'color' : 'bw';
        if (! $new && ! $all[$other.'_enabled']) {
            throw ValidationException::withMessages(['color' => PrintOptions::COLORS[$color].' kapatılamaz: en az bir baskı türü açık olmalıdır. Önce '.PrintOptions::COLORS[$other].' baskıyı açın.']);
        }

        $saved = json_decode((string) Settings::get('print'), true);
        $saved = is_array($saved) ? $saved : [];
        $saved[$key] = $new;
        Settings::put(['print' => json_encode($saved, JSON_UNESCAPED_UNICODE)]);
        $this->cache = null;
        Activity::log('print.color', null, [$key => $new]);

        return $new;
    }
}
