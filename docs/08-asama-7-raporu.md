# Aşama 7 raporu: e-postalar, mesajlar, müşteriler

## Yapılanlar

**Tek e-posta şablonu (§7)**
- Tüm e-postalar `App\Mail\SiteMail` ile üretilir. Görünüm `resources/views/emails/site.blade.php`, düz metin sürümü de vardır.
- Logo, ana renk, site adı ve alt bilgi (e-posta, telefon, adres) yönetim ayarlarından gelir.
- E-postalar kuyruktan gider (3 deneme). Her gönderim, başarılı ya da hatalı, `mail_logs` tablosuna yazılır.
- Türler:
  - sipariş alındı (müşteri; ilerleme çubuğu, ürünler, takip düğmesi)
  - yeni sipariş (yönetici; "yanıtla" müşteriye gider)
  - sipariş durumu (her adımda, yönetici "müşteriye bildir" işaretlediyse)
  - yeni mesaj (yönetici)
  - mesaj yanıtı (müşteri)
  - hoş geldiniz
  - parola sıfırlama
  - deneme
- **Yönetici alıcıları:** Ayarlar › E-posta › bildirim adresleri. Bu boşsa iletişim e-postası kullanılır.

**İletişim ve mesajlar**
- *`/iletisim` sayfası:*
  - İletişim bilgileri (telefon, WhatsApp, e-posta, adres, harita, çalışma saatleri) ve form.
  - Sayfa açıklaması ile onay metni Ayarlar › İletişim'den gelir.
  - Bot tuzağı alanı ve hız sınırı vardır (dakikada 3, saatte 20).
- *Sipariş numarası:* Formda isteğe bağlıdır. Mesaj siparişe yalnız aynı e-posta veya telefona aitse bağlanır.
- *Konu sayfası:* Her mesaj bir konu açar ve müşteri gizli bağlantıyla (`/mesaj/{id}/{token}`) konuşmayı sürdürür. Yöneticinin yanıtı bu bağlantıyla e-postaya gider.
- *Hesabım › Mesajlarım:* Üyenin hesabından veya e-postasından açılan konular listelenir.
- *Yönetim › Mesajlar:*
  - Durum sekmeleri: Yanıt bekliyor, Yanıtlandı, Kapandı, Tümü; ayrıca arama.
  - Okunmamış sayısı menüde rozet olarak görünür.
  - Konu sayfasında yanıt (isteğe bağlı olarak kapatarak), yeniden açma ve silme yapılır. Gönderenin üyelik ve sipariş bağlantıları ile diğer mesajları da görünür.
  - Müşteri yeniden yazarsa konu "Yanıt bekliyor"a döner ve yöneticiye yeniden bildirim gider.

**Müşteriler (`/yonetim/musteriler`)**
- *Liste:* sipariş sayısı ve toplam tutar (iptaller hariç), son giriş. Arama (ad, e-posta, telefon) ve sıralama (yeni, sipariş, tutar) yapılabilir.
- *Ayrıntı:* siparişler, adresler, mesajlar.
- *Hesabı kapat / aç:* Kapalı hesap giriş yapamaz, açık oturumları silinir.

**Diğer**
- *Yönetim › E-posta kayıtları:* tür, durum ve alıcıya göre süzülür; hata ayrıntısı görünür. Son 24 saatte hata varsa uyarı çıkar.
- *Ayarlar › E-posta › Deneme e-postası:* kaydedilen SMTP ile kuyruğa koymadan gönderir; hata varsa ekranda gösterir.
- *Gösterge paneli:* bugünkü sipariş (sayı ve tutar), açık sipariş (bugün teslim edilecekler dahil), okunmamış mesaj, satıştaki ürün sayısı, son siparişler, son işlemler.

## Testler
- `tests/Feature/MailAndMessageTest.php` dosyasında 8 test var. Kapsadıkları:
  - sipariş ve durum e-postalarının alıcıları
  - şablonların çizimi ve günlük kaydı
  - iletişim formu ve bot tuzağı
  - yönetici yanıtı, müşterinin devamı, kapatma ve silme
  - sipariş bağlantısının yalnız sahibine kurulması
  - hoş geldiniz e-postası
  - müşteri listesi, hesap kapatma, e-posta kayıtları ve deneme e-postası
- Toplam: 79 test, hepsi geçiyor.

## Sunucuda yapılacak
- Yönetim › Ayarlar › E-posta'ya SMTP bilgileri girilir (Gmail için uygulama şifresi), sonra deneme e-postası gönderilir.
- E-postalar kuyruktan gider; `default` kuyruğunu dinleyen çalışan (supervisor) gerekir. Kurulumu Aşama 9'da.
