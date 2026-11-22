# NEWTIC – Her müşteriye bağımsız Hostinger kurulumu (Model A)

Bu doküman tek bir müşteriye değil, herhangi bir yeni domain için ilk kuruluma
uygulanır. Polaris, Sinay veya başka mağazanın kullanıcıları ve ürünleri asla
taşınmaz. Her kurulumda ayrı hosting/dizin, MySQL, APP_KEY, SMTP ve ödeme/kargo
bilgileri vardır.

## Ön hazırlık

- Geçerli HTTPS sertifikalı yeni domain ve BOŞ public_html.
- PHP CLI/web 8.3+, Composer, Git, gerekli PHP uzantıları.
- Yeni, boş ve sadece bu müşteriye ait MySQL veritabanı.
- Laravel proje klasörü kesinlikle public_html dışında.
- Hostinger'de Node.js gerekmez; GitHub Actions derlenmiş Vite paketi verir.
- Mevcut public_html dosyaları veya Hostinger default.php varsa önce yedekleyin.
  Script mevcut site dosyalarının üzerine ASLA yazmaz.

Örnek klasörler (MUSTERI-DOMAINI kısmını gerçek domainle değiştirin):

    ~/domains/MUSTERI-DOMAINI/newtic/
    ~/domains/MUSTERI-DOMAINI/public_html/

## 1. GitHub kaynak kodları

    cd ~/domains/MUSTERI-DOMAINI
    git clone --branch main --single-branch https://github.com/kiddiewise/newtic.git newtic
    cd newtic
    cp .env.example .env
    chmod 600 .env
    nano .env

Her müşterinin kendi ayarları:

    APP_NAME="Müşteri Mağazası"
    APP_ENV=production
    APP_KEY=
    APP_DEBUG=false
    APP_URL=https://MUSTERI-DOMAINI

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=<MUSTERI_YENI_DB>
    DB_USERNAME=<MUSTERI_DB_USER>
    DB_PASSWORD=<GIZLI_SIFRE>

    SESSION_DRIVER=database
    CACHE_STORE=database
    QUEUE_CONNECTION=database
    MAIL_MAILER=log

    NEWTIC_CHECKOUT_TEST_MODE=true
    NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false
    NEWTIC_PAYTR_WEBHOOKS_ENABLED=false
    NEWTIC_SHIPPING_DUMMY_ENABLED=false
    NEWTIC_MARKETING_SEND_ENABLED=false

APP_KEY ilk kurulumda boş kalır ve bir defa otomatik üretilir. Var olan
mağazanın anahtarı daha sonra asla değiştirilmez. Sırları GitHub'a,
loglara veya sohbete yapıştırmayın.

## 2. Ön kontrol

    bash scripts/install-production.sh --check

Bu komut yalnızca **statik** PHP/.env kontrollerini yapar. MySQL bağlantısını,
şemanın boşluğunu, DNS yönlendirmesini, SSL sertifikasını veya webroot'u tek başına
doğrulamaz. `--install` gerçek veritabanına bağlanır ve herhangi bir tablo/görünüm
tespit ederse migration işlemine **başlamadan** durur. Kısmi kurulum veya mevcut
mağaza DB'si için yeniden kurulum yapılmaz; önce verilerin yedeği alınarak
durum incelenir.
İlk gerçek veritabanı tabloları --install sırasında oluşturulur.

## 3. Vite CSS/JS kurulum: iki seçenek

### Node.js sunucuda bulunuyorsa

    bash scripts/install-production.sh --install

### Hostinger'de Node.js bulunmuyorsa

1. GitHub kiddiewise/newtic -> Actions -> Tests and assets -> kullanılan
   main commit için başarılı çalışmayı açın.
2. Artifacts içinden newtic-install-assets dosyasını indirin.
3. İndirilen ZIP'in içindeki newtic-install-assets.tar.gz dosyasını
   Hostinger File Manager üzerinden newtic/ klasörüne yükleyin.
4. Aşağıdaki komutu kullanın:

    bash scripts/install-production.sh --install --assets-bundle "$PWD/newtic-install-assets.tar.gz"

Paketin içindeki build/ otomatik newtic/public/build/ klasörüne açılır;
newtic/app/public/build diye bir yol YOKTUR. Arşiv bu Git commit'ine
ait SHA etiketi taşır. Yanlış sürümün CSS/JS dosyaları reddedilir.

Bu kurulum Composer paketlerini lock dosyasından yükler, benzersiz APP_KEY,
migrations, view/config cache işlemlerini yapar. PHP exec() fonksiyonu
kapalı Hostinger sunucularında artisan storage:link çağırmaz; gerekirse
denetimli şekilde shell ln -s kullanır. Mevcut kullanıcı dosyalarını silmez.

## 4. BOŞ webroot'a yayınlama

Domainin document root'u doğrudan uygulamanın public/ klasörü ise
webroot betiği gerekli değildir. Hostinger ayrık public_html kullanıyorsa:

    bash scripts/prepare-hostinger-webroot.sh --webroot "$HOME/domains/MUSTERI-DOMAINI/public_html"

Yeni webroot scripti static build, assets ve uploads storage klasörlerini
webroot'a FİZİKSEL olarak kopyalar; bunlara symlink oluşturmaz. Hostinger
static symlink nedeniyle CSS/JS dosyalarına HTTP 404 döndürebiliyor.
İlk kurulum scripti bunu engeller.

Sistem yeni .env içine NEWTIC_PUBLIC_STORAGE_ROOT ayarını otomatik
ekler; bu klasör public_html/storage olur. Yeni görsel yüklemeleri de
gerçek public_html/storage altına kaydedilir. Uygulamanın .env, vendor,
.git, storage/app/private gibi dosyaları webroot dışında kalır.

Webroot doluysa script durur; mevcut siteyi silmez veya üzerine yazmaz.

## 5. HTTPS üzerinden dosya doğrulaması (zorunlu)

    bash scripts/verify-public-assets.sh

Bu komut gerçek HTTPS adresinden CSS ve JavaScript dosyalarını GET ile
indirerek sunucudaki aynı dosyalarla birebir karşılaştırır. 404, yanlış
mimetype/HTML yönlendirmesi, eski önbellek veya sürüm uyuşmazlığını yakalar.
Fiziksel public uploads varsa geçici test dosyasının HTTPS erişimini
de kontrol edip dosyayı siler.

Başarılı sonuç: NEWTIC_PUBLIC_ASSETS_OK: https://MUSTERI-DOMAINI

Bu sonuç olmadan ilk kurulumu tamam kabul etmeyin. Ek güvenlik kontrolü
`/.env`, `/.env.example`, `/composer.json`, `/artisan`,
`/vendor/autoload.php` ve `/storage/logs/laravel.log` adreslerinin
HTTP 403, 404 veya 410 döndürdüğünü de doğrular. Bu dosyalar internetten
sunuluyorsa kurulum kesinlikle tamam değildir.

## 6. Görsel ilk kurulum sihirbazı

Yalnız boş ve henüz kurulmamış mağazada:

    php artisan newtic:setup-token

Telefondan Hostinger File Manager kullanacak yöneticiler için:

    php artisan newtic:setup-token --private-file

İkinci seçenekte 2 saatlik tek kullanımlık kod yalnızca özel dosyada olur:
newtic/storage/app/private/newtic-setup/mobile-install-code.txt
Bu kodu paylaşmayın. Sonra https://MUSTERI-DOMAINI/install açın.
Kurulum kodu, sunucu kontrolleri, mağaza adı ve ilk yönetici bilgileriyle
ilk kurulumu tamamlayın. Ardından /install kalıcı olarak 404 döner.

Gerçek PAYTR tahsilatı, kargo ve kampanya gönderimi ile SEO indeksleme
varsayılan kapalıdır. Bunlar ayrı kabul testlerinden sonra açılır.

## 7. Diğer zorunlu işletim kontrolleri

Her mağazanın bağımsız Cron Jobs kaydında, gerçek PHP ve uygulama yoluyla
her dakika php artisan schedule:run çalıştırın. SMTP, Google OAuth
(callback: https://MUSTERI-DOMAINI/auth/google/callback), banka, kargo,
PAYTR, Merchant Center ve SEO ayarlarını o müşteriye özel yapılandırın.
SPF/DKIM/DMARC ve onay kayıtları olmadan toplu e-posta açmayın.

## 8. Sonraki sürümlerde CSS/JS tekrar yayınlanmalı

Güvenle test edilip güncellenmiş aynı SHA'lı kod ve Vite derlemesinden sonra:

    bash scripts/publish-webroot-assets.sh --webroot "$HOME/domains/MUSTERI-DOMAINI/public_html"
    bash scripts/verify-public-assets.sh

Yayınlama yalnızca build ve assets içeriğini yeniler. Müşteri uploads
klasörünü, kullanıcıları, siparişleri, DB ve .env dosyasını değiştirmez.

Bu araçlar ilk kurulum ve derleme yayınlama içindir. Tüm müşteri
sitelerine otomatik sürüm güncelleme/geri dönüş ayrı geliştirme konusudur;
Sinay'a özel CI ve Polaris staging betikleri başka domainlerde kullanılmaz.
