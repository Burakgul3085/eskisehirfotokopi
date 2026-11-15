<?php

namespace App\Domain\Catalog;

use App\Models\Category;
use App\Models\CategoryAlias;
use App\Support\Text;

/**
 * Başlangıç kategori düzeni: en fazla iki kat. Yayınevi kategori değildir (her yayınevinin kendi sayfası var);
 * kitap türü (Soru Bankası, Deneme…) ve ders (Matematik…) de kategori değil, liste süzgecidir.
 * Yönetici bu listeyi Kategoriler sayfasından değiştirebilir; install() yalnız eksikleri ekler.
 */
final class CategoryPlan
{
    public const READING = 'Okuma Kitapları';

    public const EXAM = 'Sınav Hazırlık';

    /** Sınav adı okunamayan sınav kitapları (Paragraf Soru Bankası…) */
    public const EXAM_GENERAL = 'Genel';

    /** grup => [kategori => eşlenecek okunan adlar] */
    public const TREE = [
        self::READING => [
            'Edebiyat' => ['roman', 'oyku', 'hikaye', 'siir', 'edebiyat', 'turk edebiyati', 'dunya edebiyati', 'klasikler', 'novel', 'fiction'],
            'Tarih' => ['dunya tarihi', 'avrupa tarihi', 'osmanli tarihi', 'turk tarihi', 'yakin tarih', 'history'],
            'Felsefe' => ['toplum ve dusunce', 'dusunce', 'philosophy'],
            'Psikoloji' => ['insan davranislari', 'psychology'],
            'Sosyoloji' => ['spor sosyolojisi', 'antropoloji', 'kulturel calismalar', 'sociology'],
            'Popüler Bilim' => ['bilim', 'fizik', 'noro bilim', 'norobilim', 'kozmoloji', 'astronomi', 'evrim', 'science'],
            'Dil ve Dilbilim' => ['dilbilim', 'dil felsefesi', 'dil', 'linguistik', 'linguistics'],
            'Sanat ve Tiyatro' => ['tiyatro', 'oyunculuk', 'sahne sanatlari', 'sinema', 'sanat', 'muzik', 'resim', 'art'],
            'Siyaset ve Ekonomi' => ['siyaset', 'siyaset bilimi', 'ekonomi', 'uluslararasi iliskiler', 'hukuk', 'politics', 'economics'],
            'Din ve Mitoloji' => ['din', 'mitoloji', 'ilahiyat'],
            'Biyografi ve Anı' => ['biyografi', 'ani', 'otobiyografi', 'hatirat'],
            'Eğitim' => ['egitim', 'pedagoji'],
            'Çocuk ve Gençlik' => ['cocuk', 'genclik', 'cocuk kitaplari'],
        ],
        self::EXAM => [
            'TYT' => [], 'AYT' => [], 'LGS' => [], 'KPSS' => [], 'ALES' => [], 'DGS' => [], 'YDS ve YDT' => ['yds', 'ydt', 'yokdil'],
            'Ara Sınıflar' => ['9 sinif', '10 sinif', '11 sinif', '12 sinif', 'lise'], 'Ortaokul' => ['5 sinif', '6 sinif', '7 sinif', '8 sinif'],
            'İlkokul' => ['1 sinif', '2 sinif', '3 sinif', '4 sinif'],
        ],
    ];

    /** Başlangıç düzenini kurar (var olanlara dokunmaz); kurulan/var olan kategorilerin kimlikleri. @return list<int> */
    public static function install(CategoryTree $tree): array
    {
        $ids = [];
        foreach (self::TREE as $group => $children) {
            $parent = $tree->ensurePath([$group]);
            $ids[] = $parent->id;
            foreach ($children as $name => $aliases) {
                $cat = $tree->ensurePath([$group, $name]);
                $ids[] = $cat->id;
                foreach ($aliases as $alias) {
                    if (! CategoryAlias::where('match_key', $alias)->exists() && Text::key($alias) !== $cat->normalized_name) {
                        CategoryAlias::create(['match_key' => $alias, 'category_id' => $cat->id]);
                    }
                }
            }
        }

        return $ids;
    }

    /** İki grup da duruyorsa bir şey yapmaz; biri silinmişse (ör. tüm kategoriler silindi) düzeni yeniden kurar. */
    public static function ensure(CategoryTree $tree): void
    {
        $groups = Category::whereNull('parent_id')->whereIn('normalized_name', [Text::key(self::READING), Text::key(self::EXAM)])->count();
        if ($groups < 2) {
            self::install($tree);
        }
    }

    /** Başlangıç düzenindeki bir grup veya kategori mi? */
    public static function isPlanned(Category $category): bool
    {
        foreach (self::TREE as $group => $children) {
            if ($category->normalized_name === Text::key($group)) {
                return true;
            }
            foreach (array_keys($children) as $name) {
                if ($category->normalized_name === Text::key($name)) {
                    return true;
                }
            }
        }

        return false;
    }

    /** Sınav grubu (yoksa null). */
    public static function examGroup(): ?Category
    {
        return Category::whereNull('parent_id')->where('normalized_name', Text::key(self::EXAM))->first();
    }
}
