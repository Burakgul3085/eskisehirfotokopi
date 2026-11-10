# Aşama 6 raporu: fotokopi modülü

## Yapılanlar

B2B projesindeki fotokopi akışı bu projeye taşındı ve uyarlandı (K1).

**Müşteri: `/fotokopi`**
- **Yükleme:** Çoklu belge sürükle-bırak veya seçerek yüklenir. Desteklenen türler: PDF, Word, PowerPoint, Excel, OpenDocument, JPG, PNG. Dosya başına sınır yönetimden ayarlanır, varsayılan 200 MB.
- **Kesintiye dayanıklı yükleme:** Belgeler 8 MB'lık parçalarla gönderilir. Bağlantı koparsa yükleme kaldığı yerden sürer; sunucu "şuradan devam et" yanıtı verir.
- **Tür denetimi:** Dosya türü içerikten denetlenir. Uzantısı PDF olan ama içeriği PDF olmayan dosya reddedilir.
- **İşleme:** Belge arka planda, `print` kuyruğunda işlenir. Word ve benzeri belgeler LibreOffice ile PDF'e çevrilir. Sayfa sayısı `pdfinfo` ile çıkar; araç yoksa PDF içinden sayılır. İlk sayfa önizlemesi `pdftoppm` ile üretilir.
- **Seçenekler:** renk (S-B / renkli), tek yüz / önlü arkalı, bir yüze 1/2/4/8 sayfa, A4/A3 (yönetimden açılırsa), sayfa aralığı, spiral cilt, kopya, not.
- **Fiyat:** Her değişiklikte sunucudan alınır; tarayıcı fiyat hesaplamaz.
  - Kademe, basılacak sayfa sayısına göre seçilir.
  - Tutar = kademenin yaprak fiyatı × yaprak × kopya, artı kopya başına spiral ücreti.
  - Özel teklif eşiği aşılırsa fiyat verilmez; müşteriye telefon ve WhatsApp bağlantısı gösterilir.
- **Sepet ve sipariş:**
  - Baskı işi sepete kitaplarla aynı satır yapısında girer (`print:{iş}`); fiyat sepette her gösterimde güncel ayarla yeniden hesaplanır.
  - Siparişte iş siparişe bağlanır; belgenin saklama süresi uzar.
- **Sahiplik:** Belge, oturumdaki rastgele bir anahtara bağlıdır. Misafir de yükleyebilir; başka oturum belgeye erişemez.
- **Telif onayı:** Yüklemeden önce müşteri onay kutusunu işaretlemek zorundadır.
- **Ön yüz:** Bileşen Alpine'in CSP sürümüne göre yeniden yazıldı (`resources/js/print-order.js`). Düğmeler `data-key` / `data-value` öznitelikleriyle çalışır; her belgenin görünür alanları önceden hesaplanır.

**Yönetim**
- *Yönetim › Fotokopi:*
  - Fotokopi açık/kapalı; S-B ve renkli ayrı ayrı açılıp kapanır (makine arızası için).
  - Yaprak başı fiyat kademeleri; üst kademe pahalıysa uyarı gösterilir.
  - Spiral cilt fiyatı, A3 ve çarpanı, özel teklif eşiği ve metni.
  - Dosya boyutu, en fazla kopya sayısı, saklama süreleri, sayfa metinleri.
- *Sipariş ayrıntısı:* Her baskı kalemi için "Baskı dosyasını indir" bağlantısı (çevrilmiş PDF veya özgün dosya). Teslimat fişinde baskı özeti yazar.
- `php artisan fotokopi:temizle` günlük 04:20'de çalışır. Siparişe dönüşmeyen belgeler 7 gün, siparişteki belgeler 30 gün saklanır; ikisi de yönetimden değiştirilebilir.

## B2B'den farklar
- KDV, fatura ve "en düşük baskı tutarı" kaldırıldı. Sitede ödeme altyapısı yok; en düşük sipariş tutarı teslimat ayarlarından gelir.
- Veritabanı sepeti yerine oturum sepeti kullanılıyor; belge sahipliği oturum anahtarıyla.

## Düzeltilen hata
Sepet nesnesi denetleyici kurucusunda alındığında, Laravel denetleyiciyi rota üzerinde sakladığı için satır önbelleği istekler arasında taşınıyordu. Sepet artık metot parametresiyle alınıyor.

## Sunucu notu (Aşama 9)
Ubuntu'da şu paketler kurulacak: `libreoffice-writer libreoffice-calc libreoffice-impress poppler-utils`. Kuyruk işçisi `print` kuyruğunu da dinlemeli. Yerel makinede bu araçlar yok: PDF sayfa sayımı yedek yoldan çalışıyor, Word çevirisi ve önizleme sunucuda çalışacak.

## Testler
`tests/Feature/PrintTest.php` (6 test) şunları kapsıyor:
- Fiyat hesabı: kademe, önlü arkalı, bir yüze çok sayfa, spiral, sayfa aralığı, özel teklif.
- Parçalı yükleme, PDF ve Word, sepet, sipariş ve dosya indirme.
- İçerik ve tür reddi, oturum sahipliği.
- Fotokopi kapatılınca sepetten düşme ve süresi dolan belgenin silinmesi.
- Yönetim ayar doğrulaması.

Toplam 71 test geçiyor. Tarayıcıda denendi: örnek PDF yüklendi, sayfa sayısı çıktı, fiyat geldi, seçenek değişince fiyat yenilendi, sepete eklendi ve başlıktaki sepet sayacı güncellendi.
