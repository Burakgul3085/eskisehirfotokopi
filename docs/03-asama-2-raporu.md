# Aşama 2 Raporu — Katalog çekirdeği

Tarih: 2026-11-11 · Testler: **33 geçti** (224 doğrulama)

## Yapılanlar

**Veri modeli** (`2026_11_11_000100_create_catalog_tables`)
- `categories`: sınırsız derinlik, kökten kendisine kimlik yolu (`path = "3/12/40/"`). Alt ağaçtaki tüm ürünler tek `LIKE` sorgusuyla bulunur. Sıra, menüde göster/gizle, görsel, SEO alanları.
- `publishers` + `publisher_aliases`: `match_key`, adın "yayınları / yayınevi / yayıncılık / ekran görüntüleri" ekleri atılmış normal hâlidir. "HIZ YAYINLARI", "Hız Yayınevi" ve "hiz" aynı yayınevine düşer. Rakip sitelerdeki tekrarlı yayınevi sorunu böylece önlenir.
- `authors`: içe aktarmada kendiliğinden oluşur, aynı ad iki kez açılmaz.
- `products`: ürün kodu ve ISBN benzersiz. **İki fiyat** (siyah-beyaz / renkli) ve iki üstü çizili eski fiyat (K3). Durum: taslak / yayında / pasif. Satış ve görüntülenme sayaçları. Arama için normalize edilmiş `search_text`. Görsel anahtarı ve boyutları.

**Kurallar**
- Fiyatı olmayan ürün **yayına alınamaz** (formda ve toplu işlemde). Yalnız fiyatı girilmiş baskı tipi satılır (K2, K3).
- İndirim yüzdesi gerçek fiyatlardan hesaplanır (rakip sitelerdeki yanlış "%40" sorunu olmaz).
- Görseller: orijinal **saklanmaz**. 320 / 640 / 1400 px **WebP** üretilir. 10.000 ürünün orijinalleri yaklaşık 100 GB tutardı; WebP sürümleri birkaç GB. Her yeni yüklemede yeni anahtar üretilir, önbellekte eski görsel kalmaz.

**Yönetim ekranları**
- **Ürünler:** özet kartları (toplam, yayında, taslak, fiyatı eksik, görseli eksik). Arama: ad, ISBN, kod, yazar, yayınevi. Filtreler: yayınevi, kategori (alt kategoriler dahil), durum, fiyat. Toplu işlemler: yayına al, pasif, taslak, öne çıkar, kategoriye taşı, sil. Düzenleme formunda mockup yükleme ve önizleme.
- **Excel fiyat şablonu (K2):** "Şablonu indir" o anki filtreye uyan ürünleri `.xlsx` olarak verir (ör. yalnızca fiyatı eksikler). Sarı sütunlar: S-B fiyat, renkli fiyat ve iki eski fiyat. Geri yüklemede eşleştirme ID ile yapılır. **Boş hücre değişmez, "-" fiyatı siler.** "149,90", "149.9" ve "1.250" biçimleri anlaşılır. İsteğe bağlı olarak fiyatı girilen taslaklar yayına alınır. Hatalı satırlar listelenir, diğer satırlar işlenir.
- **Kategoriler:** girintili ağaç, ürün sayısı, yukarı/aşağı sıralama, alt kategori ekleme, taşıma (alt ağaçla birlikte; kendi altına taşınamaz). Alt kategorisi veya ürünü olan kategori silinemez.
- **Yayınevleri:** logolu ızgara, arama, "logosu olmayanlar" filtresi, takma adlar, **birleştirme** (ürünler taşınır, eski ad takma ad olur), öne çıkarma (ana sayfa logo şeridi), sıra.
- **Yazarlar:** ad düzeltme; aynı ad yazılırsa iki kayıt birleştirilir.

**İçe aktarma için hazır altyapı**
- `CategoryTree::ensurePath(['TARİH', 'Avrupa Tarihi', …])` yolu bulur, eksikleri oluşturur. Büyük/küçük harf ve Türkçe karakterden bağımsızdır. Tamamı büyük harfli adları düzgün yazıma çevirir; KPSS, YKS gibi kısaltmaları korur.
- `Publisher::findByName()`, `Author::resolve()`.

## Sıradaki: Aşama 3 — Toplu klasör yükleme ve OCR
