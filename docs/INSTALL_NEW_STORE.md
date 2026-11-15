# NEWTIC – Yeni bağımsız mağaza kurulumu

Bu rehber Sinay Design gibi farklı marka/domain için sıfırdan NEWTIC kurulmasını anlatır.
Polaris ürünleri, müşterileri ve şifreleri kopyalanmaz. Güvenli tarayıcı kurulum sihirbazı v1.0 mevcuttur;
canlı ödeme/kargo etkinleştirmesi bu aşamada yoktur.

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

E-posta merkezi kendi şifrelenmiş SMTP ayarlarını kullanır. MAIL_MAILER=log
varsayılanı diğer Laravel e-postalarını kapalı tutar. Yönetici SMTP testini
geçip açıkça etkinleştirmeden otomatik müşteri e-postası gönderilmez.

## 3. Ön kontrol ve kurulum

    bash scripts/install-production.sh --check
    bash scripts/install-production.sh --install

İlk komut güvenli, salt-okunur ön kontroldür; dosya/DB değiştirmez.
İkinci komut, GitHub'da sürüm kontrolündeki `composer.lock` üzerinden tüm PHP
bağımlılıklarını (Google girişindeki `firebase/php-jwt` dahil) otomatik kurar,
PHP uzantılarını ve oturum imzası doğrulama sınıflarını kontrol eder. Ardından
asset derlemesi, benzersiz APP_KEY, migration, storage ve Laravel cache
hazırlığını yapar. Demo seeder'ı çalıştırmaz.

**Yeni domainlerde JWT veya başka Composer paketlerini elle kurmayın.**
Paket sürümleri yalnızca geliştirme dalında `composer.json` +
`composer.lock` birlikte güncellenerek, CI testlerinden geçirilerek yayınlanır.
Kurulum ön kontrolü bu dosyalardan biri eksik veya tutarsızsa güvenle durur.
PHP/Composer araçlarının hosting üzerinde ilk kez hazır edilmesi gerekir;
sonrası yukarıdaki kurulum betiğince yürütülür.

Google Cloud OAuth yapılandırması paket kurulumundan ayrıdır. Her mağazanın
HTTPS domainine karşılık gelen **tam** `https://MAGAZA-DOMAINI/auth/google/callback`
URI adresini Google Cloud > Clients > Authorized redirect URIs içinde
yetkilendirin. Admin > Google Giriş panelinden o mağazaya ait Client ID ve
Client Secret'ı kaydedin. Geçici test domainleri için Google Cloud test
kullanıcısı gerekir; gerçek domainin OAuth yayın ayarları ayrıca yapılır.

Bu ilk kurulum altyapısıdır, var olan müşteri için otomatik sürüm güncelleme
veya veritabanı taşıma aracı değildir. Kurulum SSH ve tarayıcı olarak iki kademelidir:
SSH sunucuyu, Composer/DB/.env/APP_KEY hazırlığını tamamlar; kurulum sihirbazı ise
mağaza ismi, ilk yönetici hesabı ve güvenli kilitlemeyi yapar.

## 4. Web kökü (document root)

Tercih edilen domain kökü: <uygulama>/public.
Hostinger zorunlu public_html kullanıyorsa yalnızca BOŞ webrootta:

    bash scripts/prepare-hostinger-webroot.sh --webroot "/home/USER/domains/sinaydesign.com/public_html"

Mevcut index.php, .htaccess, build/assets/storage girişlerini
ÜZERİNE YAZMAZ; dosya silmez. Hostinger karşılama dosyası veya eski site
varsa önce yedekleyip elle inceleyin.
Uygulama .env, vendor ve private kodu internete açılmaz.

## 5. Tarayıcı kurulum sihirbazı – ilk yönetici ve marka kimliği

**Önemli sıralama:** DNS/webroot'u gerçek ziyaretçilere açmadan ÖNCE
`bash scripts/install-production.sh --install` ile sunucu, veritabanı ve
migration hazırlığını tamamlayın. Ardından yalnızca gizli SSH oturumunda:

    php artisan newtic:setup-token

Bu komut yalnızca **boş ve daha önce kurulmamış** NEWTIC mağazasında çalışır;
60+ karakterlik tahmin edilemeyen bir kurulum anahtarını sadece terminale yazar.
Gerçek anahtarın yalnız SHA-256 özeti, 2 saatlik süresiyle birlikte
`storage/app/private/newtic-setup/pending.json` dosyasına kaydedilir.
Anahtarı GitHub, logs, ekran görüntüsü, e-posta veya bu sohbete göndermeyin.

Sonra `https://sinaydesign.com/install` sayfasını açın:

1. SSH'de gösterilen anahtarı yapıştırın. Yanlış denemeler kısıtlıdır.
2. Sunucu, HTTPS, veritabanı, boş mağaza, PHP uzantıları, dosya izinleri,
   test ödeme modu ve gerçek kargo güvenlik koşullarının tamamını doğrulayın.
3. Mağaza adını, ilk yönetici ad/e-postasını ve güçlü şifreyi girin;
   güvenli test modu onayını verin.
4. Kurulum tamamlanınca yönetici paneline giriş yapılır ve kurulum sayfası
   **kalıcı olarak kilitlenir**. Mağaza kurulurken başka ziyaretçiler
   HTTP 503 "Mağaza hazırlanıyor" görür ve sipariş/üyelik oluşturamaz.

Sihirbaz APP_KEY'i yeniden üretmez, başka mağazadan ürün veya müşteri
taşımaz. İlk kurulum `newtic_installations` veritabanı kaydıyla atomik
kilitlenir; ek gizli dosya kilidi de yazılır. Tamamlanan `/install` 404
döndürür. Zaman aşımında kurulum bitmemişse SSH'de
`php artisan newtic:setup-token` komutu ile yeni anahtar oluşturun.

`newtic:admin` komutu yalnızca ayrı, kasıtlı manuel kurtarma için tutulur;
normal ilk kurulumda **kullanılmamalı**.

Admin > SEO Ayarları: mağaza adı, meta bilgiler, indeksleme.
Admin > Logolar: markanın masaüstü ve mobil logosu.
Ana sayfa, sosyal bağlantılar, WhatsApp, kategoriler bu mağazaya özeldir.

Yeni mağaza varsayılan olarak noindex kullanır; gerçek içerik ve SEO
kontrol edilmeden kapatmayın. Hostinger geçici domainleri zorunlu noindex.
Polaris ürünleri, WhatsApp numarası ve sosyal linkleri yeni kuruluma gelmez.

## 6. E-posta ve müşteri bildirimleri

Admin > E-posta ve Bildirimler üzerinden bu mağazaya ait SMTP gönderici
bilgilerini tanımlayın, test e-postasını doğrulayın ve yalnız gerekli
bildirimleri açın. Ayarlar varsayılan olarak kapalıdır; şifreli SMTP parolası
asla depolar arasında kopyalanmaz. Google giriş ve Merchant bağlantılarından
bağımsızdır. Test ortamında bütün e-postalar yalnızca tanımlı test alıcısına
yönlenir. SPF/DKIM/DMARC ayarlarını her müşteri domaininde doğrulayın.
Tam işlem ve hukuki değerlendirme listesi: docs/EMAIL_NOTIFICATIONS.md.

## 7. Zamanlanmış görevler

Her yeni müşterinin kendi Hostinger Cron Jobs ayarında HER DAKİKA çalıştırın
(PHP yolunu ve dizini bu hesaba göre değiştirin):

    cd /home/USER/domains/sinaydesign.com/newtic && /usr/bin/php artisan schedule:run >/dev/null 2>&1

GitHub bakım iş akışı sadece eski Polaris staging'e bağlıdır, diğer
müşterilerin cron görevi değildir.

## 8. Yayına geçiş kontrolleri

- HTTPS, admin login, yetki, gizli .env koruması, storage izinleri.
- Marka adı/logo, SEO, Merchant, tüm vitrin ve mobil menüler.
- Ürünler/gramajlar, sepet, sipariş, stok, IBAN, Excel, 301 yönlendirmeler.
- E-posta altyapısı mevcuttur ama varsayılan kapalıdır; SMTP testi, DNS
  ve gerçek müşteri ileti onayları kontrol edilmeden otomatik gönderimleri açmayın.
- PAYTR gerçek kart tahsilatı ve gerçek kargo gönderileri yeni domainlerde
  bilerek kapalıdır; canlı entegrasyon testleri ayrıca gerekli.
- Search Console, Merchant Center, robots/sitemap/canonical yeni domainde
  kontrol edilir; yalnızca onay sonrası indeksleme açılır.
- Gerçek MySQL yoğunluk testleri, mobil/masaüstü ve yedek/geri dönüş tatbikatı.

## Güncellemeler ve yol haritası

Bu komutlar temiz yeni kurulum içindir. Müşteri bazlı sürüm yükseltme ve
geri dönüş planı farklı bir geliştirme gerektirir.

Kurulum sihirbazı v1.0 yalnızca temiz ilk kurulum ve ilk yönetici içindir.
SMTP, PAYTR, Google giriş, kargo, katalog ve SEO yönetim panelinden kurulur.
Müşteri bazlı otomatik yazılım güncelleme/geri alma ve ilerideki kişiselleştirilmiş
ürün ihtiyaçları bu ilk sihirbaz kapsamının dışındadır.
