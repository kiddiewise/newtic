# PayTR mağaza kurulumu — NEWTIC 1.0.9

1. Mağazayı kendi HTTPS APP_URL, veritabanı ve APP_KEY değerleriyle kurun. Normal müşteri kurulumunda NEWTIC_CHECKOUT_TEST_MODE=false olur. İzole test mağazasının global test ayarı canlı tahsilatı engeller.
2. Admin > PAYTR Ödeme Ayarları bölümüne kendi merchant_id, merchant_key ve merchant_salt bilgilerinizi girin. Anahtar kaydı ödemeyi kendiliğinden açmaz.
3. Gösterilen Bildirim URL'sini PayTR Mağaza Paneli > Destek & Kurulum > Ayarlar bölümüne girin. Adres dışarıdan HTTPS ile erişilebilir olmalıdır.
4. Test modunu seçip kartla ödemeyi açın. PayTR test işlemini tamamlayın; bildirimi ve sipariş sonucunu iki panelde de doğrulayın. Test siparişleri is_test olarak ayrılır.
5. PayTR mağazanızı onayladıktan ve zorunlu yasal sayfalar tamamlandıktan sonra Canlı modunu seçip gerçek tahsilatı açıkça onaylayın. Normal müşteri kurulumunda ek PayTR .env değişikliği veya NEWTIC desteği gerekmez.
6. Ürünlerinize uygun ise taksitleri etkinleştirin: PayTR/mevzuat sınırı (0) veya 2–12 arası daha düşük üst sınır. Kart uygunluğu, oranlar ve yasal sınırlar PayTR tarafından belirlenir. Taksit yasaklı ürünlerde tek çekim kullanın.
7. Yalnızca imzalı sunucu bildirimi siparişi kesinleştirir. Yeni ödemeler kapatılsa bile mevcut işlemlerin bildirimi alınır. Mağaza bilgileri değiştirilmeden önce bekleyen işlemler sonuçlandırılmalıdır.

Resmi belgeler: https://dev.paytr.com/iframe-api/iframe-api-1-adim ve https://dev.paytr.com/iframe-api/iframe-api-2-adim.
