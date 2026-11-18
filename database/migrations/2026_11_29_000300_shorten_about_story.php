<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Hakkımızda sayfası yeniden tasarlandı: hizmetler, ilkeler ve iletişim artık ayrı bölümlerde (Ayarlar › Kurumsal
 * sayfalar). Sayfa metni yalnız "Hikâyemiz" olarak kısaltılır; yönetici metni değiştirdiyse dokunulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        $page = DB::table('pages')->where('key', 'about')->first();
        if (! $page || ! str_contains((string) $page->body, '## Ne yapıyoruz?') || ! str_contains((string) $page->body, '## Çalışma ilkelerimiz')) {
            return;
        }

        DB::table('pages')->where('key', 'about')->update(['updated_at' => now(), 'body' => <<<'MD'
{site_adi}, Eskişehir'de öğrencilerin, öğretmenlerin, velilerin ve kurumların kırtasiye, kitap ve baskı ihtiyaçlarını tek noktadan karşılamak amacıyla kurulmuş bir yerel işletmedir.

Mağaza deneyimimizi internete taşıyarak siparişlerinizi bulunduğunuz yerden vermenizi, ürünlerinizi ise kendi aracımızla kapınıza ulaştırmayı ilke edindik. Sınav kitabından okul listesine, tek sayfalık bir belgeden ciltli bir dosyaya kadar her siparişi aynı özenle hazırlıyoruz.

İnternet üzerinden kart bilgisi istemiyor, ödemeyi teslimatta alıyoruz. Baskı için yüklediğiniz belgeleri yalnızca baskı amacıyla kullanıyor, süresi dolunca sistemden siliyoruz.
MD."\n"]);
    }

    public function down(): void
    {
        // Geri alınmaz.
    }
};
