# NEWTIC — Yeni Müşteri Kurulum ve Kabul Rehberi

**Belge tipi:** Anahtar teslim ilk kurulum operasyon kılavuzu (Model A / müşteri başına bağımsız sistem)  
**Sürüm hedefi:** Yalnız resmi `newtic-customer-install.zip`  
**Örnek alan adı:** `baskafirma.com` (GERÇEK firma bilgileri ile değiştirilecek)  
**Önkoşul:** Yeni müşteri alan adı ve hosting hazır olduğunda uygulanacak. Polaris ve Sinay verileri ASLA kopyalanmaz.

## 0 — Paket ve güven sınırı

Bu paket uygulama kodu, kilitli Composer üretim bağımlılıkları, derlenmiş CSS/JS,
kontrol komutları, ilk kurulum sihirbazı, bu rehber ve SHA256 bütünlük listesi içerir.
Hostta **Node.js veya Composer zorunlu değildir**; PHP 8.3+ ve gerekli modüller şarttır.
GitHub Releases'teki `newtic-customer-install.zip.sha256` dosyasıyla ZIP'i **çıkarmadan önce**
doğrulayın: `sha256sum -c newtic-customer-install.zip.sha256`.

Pakete .env, API anahtarı, çalışan mağazanın veritabanı, kullanıcılar veya siparişler
eklenmez. Yeni bir mağaza kurarken güncelleme/yükseltme betiği kullanılmaz.

**Kurulumun tamamlanması canlı satışa geçiş onayı değildir.**
Mevcut sürümde gerçek PAYTR ve gerçek kargo açılışı ayrı geliştirme/sağlayıcı kabulü gerektirir.

## 1 — Hosting uygunluğu ve hazırlık

- Domain kontrolü müşteride; DNS kayıtları doğru sunucuya yöneliyor, HTTPS sertifikası
  geçerli, alan adı www/non-www davranışı net.
- PHP CLI **ve** web PHP 8.3+; `pdo_mysql, mbstring, openssl, fileinfo, xml,
  ctype, tokenizer` uzantıları etkin; gerekiyorsa GD, bcmath, curl, zip.
- PHP'nin `exec` işlevi kapalı olabilir; paket bunu destekler.
- Tamamen **yeni ve boş** MySQL veritabanı, yalnız o müşteriye ait DB kullanıcısı
  ve yetkiler; DB başka sitenin tablolarını içermemeli.
- SSH/terminal veya güvenli görev çalıştırma yöntemi, domainin kendi cron sistemi,
  kalıcı dosya alanı ve yedekleme olanağı.
- **Apache/LiteSpeed** ile `.htaccess` kuralları; **Nginx** ile operatör
  Laravel `public/` document root / `try_files` / PHP-FPM / gizli dosya
  bloklama kurallarını hazırlamalı. `public_html` kopyalama aracı,
  Apache uyumlu rewrite ortamlarına yöneliktir.
- Domainin web kökü önceden bir site barındırıyorsa kurulum DURUR;
  mevcut site otomatik silinmez. Önce resmi yedek ve bakım planı.

## 2 — ZIP'i yerleştirme

Örnek Linux dizin yapısı:

```text
/home/HESAP/domains/baskafirma.com/
├── newtic/          # ZIP içindeki NEWTIC/ klasörü; GİZLİ uygulama dosyaları
└── public_html/     # Yalnız internete açık statik/dinamik Laravel giriş dosyaları
```

Paketin `NEWTIC/` klasörünü `newtic/` olarak, web kökü DIŞINDA açın.
Uygulamanın `.env`, `vendor`, `storage/app/private` ve `config` dosyaları
public_html içine taşınmaz. ZIP içindeki `INSTALL-FIRST.md` özet başvuru dosyasıdır.

```bash
cd /home/HESAP/domains/baskafirma.com/newtic
php scripts/verify-customer-release.php
cp .env.example .env
chmod 600 .env
```

## 3 — Müşteriye özel .env ayarları

`.env` dosyasını **özel dosya yöneticisi veya güvenli editörle** düzenleyin.
Şifreleri terminal geçmişinde, Github'da veya sohbette göstermeyin.

```dotenv
APP_NAME="Başka Firma"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://baskafirma.com
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=YENI_MUSTERI_DB
DB_USERNAME=YENI_MUSTERI_DB_KULLANICISI
DB_PASSWORD=GIZLI_DB_PAROLASI
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
MAIL_MAILER=log
NEWTIC_CHECKOUT_TEST_MODE=true
NEWTIC_PAYTR_CARD_PAYMENTS_ENABLED=false
NEWTIC_PAYTR_WEBHOOKS_ENABLED=false
NEWTIC_SHIPPING_DUMMY_ENABLED=false
NEWTIC_MARKETING_SEND_ENABLED=false
NEWTIC_INSTALL_ALLOW_HOSTINGER_TEMP_DOMAIN=false
```

**Not:** Bu bir şablon; gerçek şifreyi örnek metindeki yer tutucuya yazın.
Aynı anahtarı .env içinde iki kez tanımlamayın. İlk kurulum APP_KEY'i benzersiz
olarak oluşturacaktır. Kurulu bir mağazanın APP_KEY'i sonradan değiştirilmez.
Veritabanı şifresi özel karakterler içeriyorsa dotenv kurallarına uygun
çift tırnak içinde kaydedin. DB anahtarlarını sohbetlere asla yapıştırmayın.

### Hostinger geçici domaininde test kurulumu

İlk kurulum provası `https://ornek.hostingersite.com` gibi geçici bir
domain üzerinde yapılacaksa yalnızca o yeni mağazanın `.env` dosyasında
`NEWTIC_INSTALL_ALLOW_HOSTINGER_TEMP_DOMAIN=true` ayarlayın.
Diğer beş güvenlik bayrağı mutlaka test/kapalı konumunda kalmalıdır.
Bu özel izin yalnız geçici domainin kurulmasına yöneliktir, gerçek
ödeme/kargo/toplu e-posta izni vermez. Yeni bir gerçek domaine
geçişte bu bayrağı `false` yapın ve yeni domainin bağımsız kurulumunu
ayrıca yapın. Kurulu bir mağazanın `APP_URL` değerini başka müşteri
mağazası yapmak için değiştirmeyin.

## 4 — Güvenli kurulum komutları

```bash
bash scripts/install-production.sh --check
bash scripts/install-production.sh --install
```

İlk komut güvenli **statik** ön kontroldür; MySQL'i henüz doğrulamaz.
İkinci komut **migration öncesi salt okunur MySQL boşluk kontrolü** yapar.
Yanlış veya dolu veritabanında işlem durur. Bu korumayı devre dışı
bırakmayın. Composer/npm gerekmez; paket production `vendor` ve Vite'ı içerir.

Ayrıca giriş bağlantısının ve sunucu izinlerinin doğru olduğundan emin olun:

```bash
php artisan migrate:status
```

## 5 — Webroot türünü seçme

**Yol A — hostingin document root ayarı düzenlenebiliyorsa:**
`/home/HESAP/domains/baskafirma.com/newtic/public` olarak ayarlayın.
Çalışan kodlar kök dizinden değil Laravel public girişinden sunulmalıdır.

**Yol B — ayrı, tamamen BOŞ public_html zorunluysa, Apache/LiteSpeed:**

```bash
bash scripts/prepare-hostinger-webroot.sh --webroot "/home/HESAP/domains/baskafirma.com/public_html"
```

Adındaki Hostinger ifadesine rağmen bu araç, belirtilen uygun fiziksel
`public_html` klasörüyle çalışır; hosting firmasının özel API'sini kullanmaz.
Dolu webroot'u silmez, mevcut müşteri yüklemelerini ezmez.
Nginx üzerinde sadece bu betiğe güvenmeyin; server block kuralları gerekir.

## 6 — HTTPS ve dosya erişimi testi

```bash
bash scripts/verify-public-assets.sh
```

Komut gerçek domainin CSS/JS dosyalarını HTTPS ile indirip byte düzeyinde
karşılaştırır, public upload erişimini ve özel dosyaların HTTP 403/404/410
döndürdüğünü kontrol eder. Farklı HTTP durum kodu, 404, HTML cevap,
DNS yönlendirme veya SSL hatasında **kurulumu tamamlandı kabul etmeyin**.
Sunucu Apache/LiteSpeed/Nginx/Cloudflare davranışları ayrı doğrulanmalıdır.

## 7 — Web kurulum sihirbazı

```bash
php artisan newtic:setup-token --private-file
```

Kısa ömürlü kurulum kodu, yalnız `storage/app/private/newtic-setup/mobile-install-code.txt`
konumunda saklanır. Bu yol herkese açık webroot'un DIŞINDADIR.
Özel dosyanın kodunu **yalnız** `https://baskafirma.com/install` ekranında girin.
İki saat sonra süresi dolarsa yukarıdaki komut yenilenerek eski anahtar iptal edilir.

Sihirbazda üç aşama vardır: kimlik doğrulama → sunucu/güvenlik kontrolleri →
mağaza adı ve ilk admin hesabı. Admin parolası en az 12 karakterli
büyük/küçük harf, rakam ve simge içermelidir. Kurulum sonrası:
- Admin hesabı yalnız bu müşterinin DB'sine kaydedilir.
- Ürünler, müşteriler ve siparişler **boş** başlar.
- SEO noindex, gerçek ödeme, kargo ve toplu kampanya gönderimi kapalı kalır.
- `/install` artık 404 döner; aynı kurulum kodu yeniden kullanılamaz.

## 8 — Kurulum sonrası doğrulama

```bash
php artisan newtic:doctor
```

Kontroller: bağımsız veritabanı ve ilk admin, izinler, HTTPS ayarları,
yazılım bağımlılıkları, Vite dosyaları, şifreleme, güvenli test bayrakları
ve varsayılan noindex. Komut hassas kimlik bilgisi yazmaz.
Başarısız kontrolde durun. Ayrıca şu sayfaları gerçek HTTPS üzerinden kontrol edin:
`/`, `/admin/login`, `/admin/sistem-sagligi`, `/sepet`,
`/kurumsal` (yayınlanan sayfalar), ve tamamlanmış `/install` (404).

## 9 — İşletim ve müşteri teslimi

- **Cron:** Her dakika ayrı müşteri hesabından `php artisan schedule:run`.
- **Yedek:** Her mağazada bağımsız DB ve medya yedeği, tercihen uzak lokasyon ve
  geri yükleme provası; başarılı gerçek yedekten sonra
  `php artisan health:backup-report --result=success --provider=external`.
- **SMTP:** Önce deneme gönderimi ve SPF/DKIM/DMARC, ardından transactional mail.
- **Google OAuth:** Yeni domaine özel console/app ve redirect URI:
  `https://baskafirma.com/auth/google/callback`.
- **PAYTR:** Yeni firma merchant bilgileri ve alan adı yetkilendirmesi; callback
  `https://baskafirma.com/odeme/paytr/bildirim`. **Mevcut sürümde canlı
  ödeme ayrı proje ve sağlayıcı onayı olmadan etkinleştirilemez.**
- **Kargo:** Müşteri hesabı, gerçek taşıyıcı sözleşmesi ve callback testleri.
  Mevcut sürümde Dummy test kısıtları bulunur, gerçek etiket açılmaz.
- **Yasal sayfalar:** Her mağazanın gerçek ünvanı, ticari kayıt/iletişim bilgileri,
  KVKK, çerez, mesafeli satış, ön bilgilendirme, iade/iptal ve tüketici koşulları
  doğrulanıp yayınlanır.
- **SEO:** Ürünler, HTTPS, domain, yasal içerik, kargo/ödeme ve sitemap
  kontrolünden sonra noindex manuel olarak açılır.
- **Veri sınırı:** Başka mağazanın .env, API anahtarları, katalog, sipariş
  veya müşteri verileri **kesinlikle** kullanılmaz.

## 10 — Sorun çözme ve geri dönüş

| Belirti | Olası neden | Güvenli eylem |
|---|---|---|
| `--check` başarısız | PHP uzantısı, .env eksik/çift, yanlış HTTPS | Dosya ve sunucu ayarını düzelt; boş DB'ye dokunma |
| MySQL güvenlik kilidi | Veritabanı dolu/yanlış/erişilemiyor | Doğru yeni DB oluştur; asla `migrate:fresh` veya `db:wipe` kullanma |
| CSS/JS 404 | Yanlış document root veya eşleşmeyen SHA | Aynı sürüm paketini doğrula; webroot'u düzelt |
| Özel dosya HTTP 200 | Kaynak kökü internete açılmış | Siteyi hemen kapat, webroot ve deny kurallarını düzelt, açığa çıkan sırları değiştir |
| `/install` 404 | Kod verilmemiş, süre dolmuş veya kurulum tamam | Yeni mağaza için token üret; kurulu mağazada sihirbazı zorla açma |
| `/install` yanlış yönlendirme | HTTPS proxy/host uyuşmazlığı | APP_URL, SSL, proxy ve SESSION_DOMAIN ayarlarını incele |
| Cron kırmızı | Zamanlayıcı henüz kurulmamış | Hosting cron'u 1 dk olacak şekilde tanımla ve sağlık panelinde tekrar denetle |
| Ödeme/kargo kullanılamıyor | Güvenli ilk kurulum koruması | Entegrasyonları zorla açma; ayrı canlı kabul prosedürüne geç |

**Geri dönüş:** Birinci kurulum başarısızsa önce nedenini kayda al, loglardan
özel bilgileri paylaşma. Oluşmuş veritabanını silmek yerine bağımsız yedek alıp
durumu incele. Kurulum öncesi/sonrası güncelleme süreçleri farklıdır.
Canlıya alınmış bir mağazanın APP_KEY'i değiştirilmez; müşteri verileri sıfırlanmaz.

## Kabul tutanağı

- [ ] Resmi GitHub ZIP SHA256 doğrulandı.
- [ ] Ayrı domain/SSL ve boş MySQL güvenli biçimde doğrulandı.
- [ ] Kaynak kodu webroot dışında; özel dosyalar HTTP ile erişilemez.
- [ ] Kurulum sihirbazı yalnız ilk kez çalıştı, admin oluşturuldu, tekrar 404.
- [ ] UI, test sepet/ödeme, ürün yükleme ve yorum izinleri manuel denendi.
- [ ] `newtic:doctor` geçti.
- [ ] Cron, yedek, SMTP ve yasal sayfalar müşteriye özel tamamlandı.
- [ ] Gerçek PAYTR/kargo ve SEO açma için **ayrı** kabul alınmadıysa kapalı.

**Bu belge bir kullanıcı teslim/ilk kurulum rehberidir; gerçek ödeme ve taşıyıcı
servislerinin tamamlandığı anlamına gelmez.**
