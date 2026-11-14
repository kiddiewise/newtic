# NEWTIC – Yeni bağımsız mağaza kurulumu

Bu rehber Sinay Design gibi farklı marka/domain için sıfırdan NEWTIC kurulmasını anlatır.
Polaris ürünleri, müşterileri ve şifreleri kopyalanmaz. Grafik kurulum sihirbazı
ve canlı ödeme/kargo etkinleştirmesi bu aşamada yoktur.

## 1. Gereksinimler

- Boş domain ve boş webroot, çalışan HTTPS sertifikası.
- PHP CLI ve web PHP 8.3+; pdo_mysql, mbstring, openssl, fileinfo,
  xml, ctype, tokenizer uzantıları; Composer; ayrı MySQL veritabanı.
- Node.js 22+ ile npm veya doğru commit'e ait test edilmiş public/build dosyaları.
- SSH erişimi; uygulama public_html klasörünün DIŞINDA bulunmalıdır.
- Kaynak yalnızca GitHub main. Eski scripts/deploy-staging.sh bu amaçla kullanılmaz.

## 2. Temiz kod ve mağaza ayarları

    cd ~/domains/sinaydesign.com
    git clone --branch main --single-branch https://github.com/kiddiewise/newtic.git newtic
    cd newtic
    cp .env.example .env

Her müşteri için yeni MySQL kullanıcısı/veritabanı tanımlayın. Başka mağazanın
.env, APP_KEY, SQL, depolama veya ödeme/kargo anahtarlarını KOPYALAMAYIN.

Örnek .env (gerçek DB şifreleri yalnızca sunucuda saklanır):

    APP_NAME="Sinay Design"
    APP_ENV=production
    APP_DEBUG=false
    APP_URL=https://sinaydesign.com

    DB_CONNECTION=mysql
    DB_HOST=127.0.0.1
    DB_PORT=3306
    DB_DATABASE=<YENI_VERITABANI>
    DB_USERNAME=<YENI_KULLANICI>
    DB_PASSWORD=<GIZLI_SIFRE>

    SESSION_DRIVER=database
    CACHE_STORE=database
    QUEUE_CONNECTION=database

    NEWTIC_CHECKOUT_TEST_MODE=true
    MAIL_MAILER=log

Yeni kurulumda APP_KEY boş bırakılır. Kurulum yalnızca boşsa benzersiz anahtar
oluşturur. Mevcut mağazanın anahtarını ASLA değiştirmeyin.
APP_DEBUG=false ve HTTPS zorunludur.

E-posta sistemi henüz hazırlanmadı. MAIL_MAILER=log gerçek posta göndermez.
Sipariş bildirimleri ve parola sıfırlama için ayrıca e-posta altyapısı gerekir.

## 3. Ön kontrol ve kurulum

    bash scripts/install-production.sh --check
    bash scripts/install-production.sh --install

İlk komut güvenli, salt-okunur ön kontroldür; dosya/DB değiştirmez.
İkinci komut Composer, asset derlemesi, benzersiz APP_KEY, migration, storage
ve Laravel cache hazırlığını yapar. Demo seeder'ı çalıştırmaz.

Bu ilk kurulum altyapısıdır, var olan müşteri için otomatik sürüm güncelleme
veya veritabanı taşıma aracı değildir.

## 4. Web kökü (document root)

Tercih edilen domain kökü: <uygulama>/public.
Hostinger zorunlu public_html kullanıyorsa yalnızca BOŞ webrootta:

    bash scripts/prepare-hostinger-webroot.sh --webroot "/home/USER/domains/sinaydesign.com/public_html"

Mevcut index.php, .htaccess, build/assets/storage girişlerini
ÜZERİNE YAZMAZ; dosya silmez. Hostinger karşılama dosyası veya eski site
varsa önce yedekleyip elle inceleyin.
Uygulama .env, vendor ve private kodu internete açılmaz.

## 5. İlk yönetici ve marka kimliği

    php artisan newtic:admin

Admin > SEO Ayarları: mağaza adı, meta bilgiler, indeksleme.
Admin > Logolar: markanın masaüstü ve mobil logosu.
Ana sayfa, sosyal bağlantılar, WhatsApp, kategoriler bu mağazaya özeldir.

Yeni mağaza varsayılan olarak noindex kullanır; gerçek içerik ve SEO
kontrol edilmeden kapatmayın. Hostinger geçici domainleri zorunlu noindex.
Polaris ürünleri, WhatsApp numarası ve sosyal linkleri yeni kuruluma gelmez.

## 6. Zamanlanmış görevler

Her yeni müşterinin kendi Hostinger Cron Jobs ayarında HER DAKİKA çalıştırın
(PHP yolunu ve dizini bu hesaba göre değiştirin):

    cd /home/USER/domains/sinaydesign.com/newtic && /usr/bin/php artisan schedule:run >/dev/null 2>&1

GitHub bakım iş akışı sadece eski Polaris staging'e bağlıdır, diğer
müşterilerin cron görevi değildir.

## 7. Yayına geçiş kontrolleri

- HTTPS, admin login, yetki, gizli .env koruması, storage izinleri.
- Marka adı/logo, SEO, Merchant, tüm vitrin ve mobil menüler.
- Ürünler/gramajlar, sepet, sipariş, stok, IBAN, Excel, 301 yönlendirmeler.
- Mail altyapısı henüz yok; gerçek müşteri bildirimleri test edilmeden satış açılmaz.
- PAYTR gerçek kart tahsilatı ve gerçek kargo gönderileri yeni domainlerde
  bilerek kapalıdır; canlı entegrasyon testleri ayrıca gerekli.
- Search Console, Merchant Center, robots/sitemap/canonical yeni domainde
  kontrol edilir; yalnızca onay sonrası indeksleme açılır.
- Gerçek MySQL yoğunluk testleri, mobil/masaüstü ve yedek/geri dönüş tatbikatı.

## Güncellemeler ve yol haritası

Bu komutlar temiz yeni kurulum içindir. Müşteri bazlı sürüm yükseltme ve
geri dönüş planı farklı bir geliştirme gerektirir.

Sonraki aşama: e-posta altyapısı. Daha sonra canlı ödeme/kargo kabul
süreçleri. En son: kolay kullanımlı kurulum sihirbazı.
