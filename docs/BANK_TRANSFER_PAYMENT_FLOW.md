# NEWTIC — Havale/EFT ve ödeme bildirimi

Tarih: 2026-11-10
Hedef: Hostinger staging `powderblue-dinosaur-538060.hostingersite.com`
GitHub: `feat/phase-01-foundation-homepage`
Production `polariskursun.com` kapsam dışıdır.

## Yönetim paneli

- **Banka Hesapları:** `/admin/bank-accounts`
  - Sınırsız sayıda banka hesabı, banka adı, hesap sahibi, TR IBAN, banka logosu,
    sıralama ve aktif/pasif durum
  - 26 karakter TR IBAN biçimi ve mod-97 kontrolü, benzersiz IBAN
  - JPEG/PNG/WebP, 2 MB ve boyut limiti; SVG veya keyfi dosya yüklemesi yok
  - Banka devre dışı bırakıldığında yeni ödeme talimatlarında gösterilmez
- **Ödeme Bildirimleri:** `/admin/payment-notifications`
  - Bekleyen / doğrulanan / reddedilen bildirimler ve menü rozeti
  - Sipariş numarası, sipariş tutarı, seçilen hesap, müşteri notu
  - Banka transferini **manuel doğrulamak için onay kutusu şart**
  - Onay: aynı veritabanı işlemi içinde bildirim `verified` ve sipariş `preparing`
  - Ret: `rejected` + zorunlu gerekçe, sipariş hâlâ `pending_payment`; tekrar bildirim yapılabilir
  - Banka kontrolü olmadan `pending_payment -> preparing` kapalı
  - Onaylı ödeme için iade sistemi yokken sipariş otomatik iptal edilemez
  - Önceki bildirimin banka adı ve IBAN'ı geçmiş kaydında korunur

## Müşteri

1. Havale/EFT seçerek sipariş oluşturur (üye veya misafir).
2. Sipariş sonrası banka kartlarını ve toplam tutarı görür.
3. Banka IBAN'ını veya açıklamaya yazılması gereken **sipariş numarasını** ikonla kopyalar.
4. Banka hesabı seçip "Ödeme Yaptım" bildirimi gönderir.
5. Bir bildirim `pending` ise tekrar gönderim yerine "Kontrol ediliyor" görür.
6. Yönetici reddederse sebebiyle yeniden bildirim yapabilir; doğrularsa "Hazırlanıyor" görür.

Misafir müşteriler için 14 gün geçerli ve HMAC imzalı, bağlantısı kopyalanabilir sipariş erişimi de bulunur. Bu bağlantı sipariş bilgilerini görüntüleme ve bildirim gönderme yetkisi taşıdığından üçüncü kişilerle paylaşılmamalıdır. İmza süresi dolduğunda veya değiştirildiğinde erişim reddedilir. Bağlantının referrer üzerinden sızmasını önlemek için sipariş ekranında `no-referrer` kullanılır.
Üye müşteriler kendi siparişlerini hesap sayfasından yeniden görüntüleyebilir.

## Test modu

`NEWTIC_CHECKOUT_TEST_MODE=true` varsayılandır. Gerçek banka havalesi göndermeyin.
Test siparişinde aynı ekran "TEST" uyarısı ve "Test Bildirimi" etiketi gösterir.
Canlı ödeme başlatmadan önce banka hesaplarının gerçek ve işletmeye ait olduğu,
alıcı unvanları, sözleşmeler, iade prosedürü ve bildirim altyapısı ayrıca doğrulanmalıdır.
Banka transferi bildirimi kendisi başına ödeme tahsilatı veya muhasebe kanıtı değildir.

## Dağıtım ve QA

- [ ] `php artisan migrate --force`: `bank_accounts`, `payment_notifications`
- [ ] `npm run build`: ödeme sayfasının JS/CSS varlıkları
- [ ] `php artisan test`: `BankTransferPaymentFlowTest` ve `CheckoutOrdersTest`
- [ ] Admin hesabı banka ekler; müşteri sipariş ekranında sıralı banka kartlarını görür
- [ ] PNG/WebP logo yükleme ve değiştirme çalışır
- [ ] Mobil 360/390px, tablet 768px ve masaüstü 1440px banka alanında taşma yok
- [ ] Telefon tarayıcısında IBAN ve sipariş numarası kopyalama çalışır
- [ ] Yetkisiz kişiler banka ayarlarını ve diğer müşterinin bildirimini düzenleyemez
- [ ] Tekrarlanan Ödeme Yaptım tıklamaları tek pending kayıt oluşturur
- [ ] Müşteri bildirimi ödeme onayı yaratmaz; manuel doğrulamadan sonra durumu güncellenir
- [ ] Retten sonra yeni bildirim eski denetim kaydını silmeden oluşturulur
- [ ] Üretimde gerçek banka hareketi teyit edilmeden sipariş hazırlanma durumuna alınmaz

Güncel branch'te yeni commit oluşması GitHub CI'nin veya Hostinger deploy'un
başarıyla tamamlandığını **tek başına kanıtlamaz**.
