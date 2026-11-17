# NEWTIC | Tam paket sürüm | 17 Kasım 2026

Sabit sürüm: `newtic-v2026.11.17-full`.

Paket: Laravel/PHP kaynak kodu, yönetim paneli, blog, SEO, PAYTR/kargo/Google entegrasyon kodu, vitrin blokları, kurulum sihirbazı, migration dosyaları, testler, Composer ve npm kilitli sürümleri, derlenmiş Vite CSS/JS ve üretim `vendor/` bağımlılıkları. İndirilen tar.gz ile SHA256SUMS.txt kontrol dosyasını birlikte saklayın.

## Mağaza verileri izole kalır
Her müşterinin `.env`, MySQL, `APP_KEY`, ürün, sipariş, kullanıcı, görsel, ödeme ve kargo bilgileri birbirinden ayrıdır. **Bu veriler ve gizli bilgiler pakete alınmaz.** Mevcut siteyi güncellerken kesinlikle yeniden kurulum yapılmaz veya `APP_KEY` üretilmez; önce sunucuda veritabanı ve kullanıcı yüklemelerinin korumalı yedeği alınır.

## Kurulum ve sürüm yükseltme
Yeni kurulum için README.md ile `scripts/install-production.sh` talimatlarını izleyin. PHP 8.3+, MySQL ve Hostinger'da `public_html` yönlendirmesi gereklidir. Mevcut Sinay Design kurulumu `sinaydesign.com/newtic` dizinindedir ve kendi veritabanı `u880685189_sinay_newtic` korunacaktır. `polariskursun.com` mevcut WooCommerce sitesine dokunulmaz.

Paket açıldıktan sonra: `sha256sum -c SHA256SUMS.txt` ile arşivin bütünlüğünü doğrulayın. Yeni kurulumda kendi `.env` dosyanızı oluşturun; eski kurulumda mevcut dosyayı koruyun. Bu sürüm etiketini değiştirmeyin, yeni sürümler için yeni etiket kullanın.
