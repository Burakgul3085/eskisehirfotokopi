<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * KVKK aydınlatma metnine ürün değerlendirmeleri ve soruları: veri kategorisi, amaç, toplama yöntemi ve saklama
 * süresi satırları. Yalnız metin hâlâ ilgili satırları içeriyorsa (yönetimden değiştirilmediyse) eklenir; iki kez
 * çalışsa da tekrarlamaz.
 */
return new class extends Migration
{
    private const MARK = 'Değerlendirme ve soru';

    public function up(): void
    {
        $page = DB::table('pages')->where('slug', 'kvkk-aydinlatma-metni')->first();
        if (! $page || str_contains($page->body, self::MARK)) {
            return;
        }
        $body = str_replace("\r\n", "\n", $page->body);
        $rows = [
            "| Pazarlama | E-bülten için verdiğiniz e-posta adresi ve onay kaydı |\n" => "| Pazarlama | E-bülten için verdiğiniz e-posta adresi ve onay kaydı |\n| Değerlendirme ve soru | Ürün değerlendirmeniz (puan, yorum, eklediğiniz fotoğraflar) ve ürünlerle ilgili sorularınız |\n",
            "| Mesaj ve taleplerinizin yanıtlanması, şikâyet yönetimi | Meşru menfaat (m.5/2-f) |\n" => "| Mesaj ve taleplerinizin yanıtlanması, şikâyet yönetimi | Meşru menfaat (m.5/2-f) |\n| Ürün sorularının cevaplanması; değerlendirme ve cevaplanan soruların adınız kısaltılarak (ör. “Ayşe K.”) ürün sayfasında yayınlanması | Meşru menfaat (m.5/2-f) ve ilgili kişinin kendisi tarafından alenileştirilme (m.5/2-d) |\n",
            'üyelik, sipariş, belge yükleme, iletişim ve e-bülten formları' => 'üyelik, sipariş, belge yükleme, ürün değerlendirme, soru, iletişim ve e-bülten formları',
            "| Mesaj ve talepler | Talebin sonuçlandırılmasından itibaren 2 yıl |\n" => "| Mesaj ve talepler | Talebin sonuçlandırılmasından itibaren 2 yıl |\n| Değerlendirme ve soru kayıtları | Yayında kaldığı süre ve kaldırılmasından itibaren 2 yıl; fotoğraflar değerlendirmeyle birlikte silinir. Değerlendirmenizi Hesabım sayfasından dilediğiniz zaman silebilirsiniz |\n",
        ];
        $changed = false;
        foreach ($rows as $old => $new) {
            if (str_contains($body, $old)) {
                $body = str_replace($old, $new, $body);
                $changed = true;
            }
        }
        if ($changed) {
            DB::table('pages')->where('id', $page->id)->update(['body' => $body, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // Metin değişikliği geri alınmaz (yönetimden düzenlenebilir).
    }
};
