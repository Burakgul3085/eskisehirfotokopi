# Aşama 3 Raporu — Toplu klasör yükleme ve OCR

Tarih: 2026-11-10 · Testler: **46 geçti** (330 doğrulama)

## Yapılanlar

**Yükleme akışı** (Yönetim › Katalog › Toplu yükleme)
1. Kök klasör sürüklenir veya seçilir. Klasör **tarayıcıda** okunur, sunucuya bir şey gitmeden özet gösterilir: kitap sayısı, yayınevi klasörü sayısı, bilgi görseli olmayanlar, mockup'ı olmayanlar, 15 MB'ı aşan dosyalar ve ilk 8 kitap.
2. Onaylanınca kitap listesi tek istekle gönderilir. **Daha önce eklenmiş kitaplar atlanır.** Her kitap klasörünün kalıcı bir parmak izi vardır (yayınevi klasörü + kitap klasörü). Yükleme yarıda kalırsa aynı kök klasör yeniden bırakılır ve kaldığı yerden sürer.
3. Dosyalar **6 paralel istekle** gönderilir. Geçici hatalar 4 kez yeniden denenir. Duraklat/sürdür düğmesi vardır.
   - Mockup tarayıcıda 1800 px'e küçültülür. Sunucu zaten en fazla 1400 px saklar; 3 MB'lık PNG yerine yaklaşık 300 KB gider.
   - Bilgi görseli OCR doğruluğu için **özgün** hâliyle gider.
4. Bir kitabın dosyaları tamamlanınca kitap `import` kuyruğuna girer ve arka planda işlenir. Ekranda iki ilerleme çubuğu vardır: gönderim ve işleme (eklendi / kontrol / hata / sırada). Gönderim bittikten sonra sayfa kapatılabilir.

**Dosya rolleri** (sunucu ve tarayıcıda aynı kural)
- "Ekran görüntüsü…" → bilgi tablosu.
- "ChatGPT…/mockup" → ürün görseli.
- Adı yalnız rakam olan dosya ("1.jpg") **kullanılmaz** (K5).
- Adı tanınmayan tek görsel kaldıysa mockup sayılır.

**Kitap işleme** (`ImportItemProcessor`)
- **OCR** (`TesseractEngine`, ücretsiz ve yerel, K10).
  - Ön işlem: görsel gri tonlamaya çevrilir ve 2 kat büyütülür. Koyu temalı ekran görüntüsü otomatik ters çevrilir. Ardından kontrast artırılır.
  - Kelimeler konumlarıyla okunur (TSV).
- **Tablo ayrıştırıcı** (`InfoSheetParser`).
  - Kelimeler satırlara, satırlar etiket ve değer sütunlarına ayrılır.
  - Etiketler bulanık eşleşir: "Yazr" ve "lSBN" yine tanınır.
  - İki satıra taşan "Türü" birleştirilir.
  - "Henüz doğrulanmadı" boş sayılır.
- **ISBN ve sayfa sayısı ikinci kez okunur.** Yalnız o hücre kırpılır ve rakam modunda okunur.
  - ISBN kontrol hanesiyle doğrulanır; ISBN-10 değerleri 13 haneye çevrilir.
  - İki okuma farklıysa kitap kontrole düşer.
- **Klasör adıyla çapraz kontrol** ("YAYINEVİ - KİTAP - YAZAR").
  - Kitap adı, yazar veya yayınevi uyuşmazsa kitap **"kontrol gerekli"** olur.
  - Uyuşmazlıkta insan yazdığı için klasör adı esas alınır; yazımı düzeltilir: "ALMANYA'NIN KISA TARİHİ" → "Almanya'nın Kısa Tarihi". KPSS, YKS, ALES, ÖABT gibi kısaltmalar korunur.
- **Yayınevi.**
  - Klasör adı ve OCR adı aynı yayınevine eşlenir: "BOĞAZİÇİ ÜNİVERSİTESİ YAYINLARI EKRAN GÖRÜNTÜLERİ" ile "Boğaziçi Üniversitesi Yayınevi" aynı kayda düşer.
  - Görseldeki düzgün yazım kullanılır.
  - Farklı bir yazım takma ad olarak eklenir.
- **Kategori.** "Tarih / Avrupa Tarihi / Almanya Tarihi" yolu kurulur; eksik kategoriler oluşturulur.
- **Ürün eşleştirme.**
  - Sıra: ISBN → ürün kodu → yayınevi ile aynı ad.
  - **Var olan ürünün fiyatı ve yayın durumu korunur.**
  - Yeni ürün **taslak** açılır; fiyatlar Excel şablonuyla gelir (K2).
- **Görseller.**
  - Mockup WebP'ye çevrilir (320/640/1400) ve özgünü silinir.
  - Bilgi görseli kontrol için 30 gün saklanır. Her gece 04:10'da `katalog:yukleme-temizle` çalışır.
- Paralel işçiler aynı yayınevini veya kategoriyi iki kez açmasın diye kısa bir kilit kullanılır.

**Kontrol ekranı**
- Bilgi görseli solda, düzenlenebilir alanlar sağdadır: ad, yazar, yayınevi, kategori yolu, ISBN, sayfa ve kod. OCR ham metni de açılabilir.
- "Kaydet ve onayla" kaydeder ve sıradaki kontrol bekleyen kitaba geçer.
- "Değiştirmeden onayla" ve "Atla" düğmeleri vardır.
- Yükleme ayrıntısında duruma göre filtre ve hatalıları yeniden işleme vardır.

**Sunucudan yükleme** (10.000 kitap için tarayıcıdan daha hızlı)
- Arşiv SFTP ile sunucuya kopyalanır, ardından:

  ```bash
  php artisan katalog:klasor-yukle /yol/KİTAPLAR
  ```

- `--hemen` seçeneği kuyruk yerine komutun içinde işler.

## Bilinmesi gerekenler

- **Bu bilgisayarda Tesseract kurulamadı.** winget yok. Chocolatey yönetici izni istiyor. İndirilen kurulum dosyasını çalıştırmaya otomatik güvenlik denetimi izin vermedi; bunu aşmaya çalışmadım. Bu yüzden:
  - OCR'ın gerçek ekran görüntüleri üzerindeki doğruluğu **henüz ölçülmedi**.
  - Ayrıştırıcı, örnek bilgi tablosunun birebir düzenini taklit eden testlerle doğrulandı: yanlış okunmuş etiket, taşan satır, ISBN düzeltmesi.
  - Yerelde OCR yoksa sistem çalışmayı sürdürür. Bilgiler klasör adından alınır ve tüm kitaplar kontrole düşer (uçtan uca denendi).
- **Sunucuda kurulum** (Aşama 9 betiğine eklenecek):

  ```bash
  apt install tesseract-ocr tesseract-ocr-tur
  ```

  `import` kuyruğu için 2–4 işçi çalıştırılacak (supervisor).
- Yerelde denemek için şunu çalıştırın:

  ```bash
  php artisan queue:work --queue=import,default
  ```

- Arama kararı: Meilisearch yerine MySQL kullanılacak (docs/01 §3 güncellendi). Ek sunucu hizmeti gerekmez; 10.000 üründe yeterince hızlıdır.

## Sıradaki: Aşama 4 — Vitrin
