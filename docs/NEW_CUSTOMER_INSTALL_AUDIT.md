# NEWTIC — Bağımsız Müşteri Kurulumu Teknik Kabul Denetimi

**Tarih:** 22 Kasım 2026  
**Varsayımsal müşteri:** `baskafirma.com`  
**Kaynak:** `kiddiewise/newtic` / `main` baz alınan sürüm `beaba04e794837ab92b970efd815ac15bee1dee4`  
**Kapsam:** Gerçek bir üçüncü taraf domaini oluşturmadan kaynak kodu, ilk kurulum, izolasyon, güvenlik ve CI doğrulaması.  
**Kural:** Polaris ve Sinay üzerinde kurulum, sıfırlama, veri taşıma veya ödeme açma işlemi yapılmaz.

## Kabul kararı

**Kurulum temeli mevcut, ancak ticari canlıya çıkış için henüz koşulsuz hazır değil.**

1. Kodda bağımsız `APP_URL`, ayrı `.env`, MySQL, anahtar, kurulum sihirbazı ve müşteri bazlı vitrin/marka verisi var.
2. CI temiz MySQL 8 + PHP 8.3, Node'suz derlenmiş asset, PHP `exec()` kapalı senaryo, fiziksel webroot ve `/install` anahtarı hazırlığını test ediyor.
3. Yeni kurulumda gerçek PAYTR kart tahsilatı ve kargo gönderimi **varsayılan kapalı** ve mevcut test kodunda belirli geçici test hostuna bağlı. Bu bir **canlıya geçiş engeli**dir, rastgele feature flag açılarak geçilmemelidir.
4. Yeni mağazanın gerçek hukuki sayfaları, ticari kimliği, iletişim verileri, SMTP, OAuth, ödeme/kargo sözleşmeleri ve SEO onayları satışı yapan firmaya ait olmalıdır.

## Bulgu tablosu

| Öncelik | Alan | Kod kontrolü | Alınan aksiyon / kabul kriteri |
|---|---|---|---|
| Kritik, giderildi (önerilen PR) | Yanlış/veri içeren MySQL'e ilk kurulum | Eski `--check` yalnız statik ayarları kontrol ediyordu; `--install` migration öncesi veritabanının boşluğunu zorunlu doğrulamıyordu | `newtic:check-fresh-db` eklendi. Salt okunur MySQL bağlantısı, seçili şema ve tüm tablo/görünümleri denetleyip dolu/ulaşılamaz DB'yi reddeder; migration ve yeni anahtar öncesi çalışır. CI temiz ve dolu DB senaryolarını kontrol eder. |
| Yüksek, giderildi (önerilen PR) | Test/gerçek servis güvenliği | Kurulum, eksik veya farklı yazılmış bazı bayrakları varsayılan olarak yorumlayabiliyordu | Beş güvenlik bayrağının açıkça beklenen `true/false` değeriyle yazılması zorunlu |
| Yüksek, giderildi (önerilen PR) | Web kökü gizli dosya sızıntısı | Var olan HTTPS varlık testi CSS/JS ve public upload kontrolünü yapıyordu | `.env`, `.env.example`, `composer.json`, `artisan`, `vendor/autoload.php`, `storage/logs/laravel.log` için HTTP 403/404/410 zorunlu |
| Kritik, açık | Canlı PAYTR | `PaytrTestAccess::HOST` yalnız Polaris staging alan adını kabul ediyor; gerçek kart tahsilatını başka domaine almak mümkün değil | Ayrı canlı ödeme tasarımı, domain bağımsız test/live geçiş politikası, callback doğrulaması ve sağlayıcı uçtan uca testleri |
| Kritik, açık | Gerçek kargo | `ShippingTestAccess::HOST` sabit; yalnız Dummy Kargo denemeleri kabul ediliyor | Gerçek kargo sağlayıcısı için ayrık yetkilendirme, callback doğrulaması, iade/hata testleri ve canlıya geçiş onayı |
| Yüksek, kurulum bazlı | Hosting uyumluluğu | Laravel PHP 8.3+, MySQL, Composer, CLI, izinler, cron gerektiriyor; fiziksel public_html kopyalama aracı Apache/.htaccess varsayıyor | Hosting türüne göre Apache/NGINX/LiteSpeed uyarlaması ve PHP CLI/web sürüm eşitliği doğrulanmalı |
| Yüksek, kurulum bazlı | Varlık derlemesi | Sürümle eşleşen Vite build GitHub Actions artifact'inden alınıyor | Tam SHA eşleşmesi ve HTTPS üzerinden birebir CSS/JS kontrolü zorunlu. Süresi dolan artifact'ten değil yeni başarılı sürümden yükleme |
| Yüksek, kurulum bazlı | Yönetici/kurulum anahtarı | `/install` tokenı yalnız özel CLI/dosyadan çıkarılıyor; hash/expiry/tek mağaza kilidi var | Tek kullanımlık token, 12+ karakterli admin parolası, kurulumdan sonra `/install` 404 |
| Yüksek, kurulum bazlı | İşletim ve yedek | Bir dakikalık `schedule:run` başka hostingte otomatik oluşmaz; sağlık paneli kendiliğinden yedek üretmez | Gerçek cron, özel veritabanı + dosya yedeği, yedek geri yükleme provası, izleme |
| Yüksek, kurulum bazlı | SEO/hukuk | İlk kurulum noindex; yasal sayfalar müşteri kimliğiyle doldurulmalı | KVKK, mesafeli satış, ön bilgilendirme, iade/iptal, çerez, şirket bilgileri ve içerik onayı tamamlanmadan indeksleme/gerçek satış açılmaz |
| Orta | Dokümantasyon tutarlılığı | `docs/ADMIN_SETUP.md` ve `docs/HOSTINGER_SETUP.md` tarihsel faz notları içeriyor | Yeni rehberde tek geçerli kaynak `docs/INSTALL_NEW_STORE.md` + son kabul raporu olacak. Eski dokümanlar tarihi not olarak ele alınmalı |
| Orta | Çok müşterili sürüm yükseltme | Sinay/Polaris özel deployment workflow'ları bulunuyor | Yeni mağaza için güvenli, genel amaçlı, yedekli rollback ve SHA bazlı upgrade süreci ayrıca ürünleştirilmeli |

## Kurulumdan önce onaylanacak ortam

- Firma mülkiyetindeki domain ve çalışan SSL/TLS; doğru DNS ve kanonik host.
- PHP **CLI ve web** sürümleri 8.3+; gereken modüller; Composer; SSH/terminal veya kontrollü sağlayıcı otomasyonu.
- Her müşteri için kendi, **tamamen boş MySQL veritabanı**, ayrı kullanıcı ve yalnız gereken yetkiler; veritabanı adları tahmin edilerek yeniden kullanılmaz.
- Uygulama kodu **public webroot dışında**; yalnız `public/` veya güvenli fiziksel `public_html` yayınlanır.
- `storage/` ve `bootstrap/cache/` yazılabilir; `public_html/storage` webde yalnız izin verilen public içerikleri sunar.
- Her mağazaya ayrı `.env`, `APP_KEY`, dosyalar, cookie/session alanı, mail ve API kimlik bilgileri.
- Güvenli test ortamı: PAYTR tahsilat, gerçek kargo, kampanya gönderimleri kapalı; SEO noindex.
- Bir dakikalık Laravel scheduler, sır/anahtar yedeği ve gerçek veri geri yükleme prosedürü.

## Kurulum kabul senaryoları

**T1 — Statik ön kontrol:** `bash scripts/install-production.sh --check` başarılı; bunun tek başına DB testi olmadığı açıkça gösterilir.

**T2 — Veritabanı negatif test:** Yetkisiz/erişilemez, yanlış şema veya içinde en az bir tablo/görünüm olan hedef DB reddedilir; hiçbir migration, admin veya müşteri verisi değiştirilmez.

**T3 — Temiz MySQL kurulum:** Gerçek boş MySQL üzerinde `--install`, şema kontrolünden sonra bağımsız APP_KEY, migration ve doğru sürümlü Vite dosyalarını hazırlar.

**T4 — Statik yayın:** Boş webroot; başka sitenin `default.php`, `.htaccess` veya yüklemelerini ASLA silmez. CSS/JS ve public medya HTTP ile doğrulanır.

**T5 — Kaynak gizliliği:** HTTP `/.env`, `/vendor/autoload.php`, `/composer.json` vb. 200 dönmez. `/install` yalnız geçerli, süresi dolmamış özel anahtarla çalışır.

**T6 — İlk kurulum:** İlk yönetici, mağaza adı ve noindex oluşturulur; varsayılan ürün, müşteri, sipariş aktarılmaz. Kurulum tekrar açılamaz.

**T7 — Yönetim:** Admin login, rol yetkileri, ürün/kategori/medya, görünüm/slider, sepet, test checkout, yasal sayfalar ve Sistem Sağlığı kontrol edilir.

**T8 — İşletim:** Cron kaydı, gerçek yedek başarı bildirimi, SMTP test gönderimi, OAuth domain callback'i, ödeme/kargo API'leri ve servislerin veri izolasyonu teyit edilir.

**T9 — Canlı çıkış:** PAYTR gerçek kart + imzalı callback, kargo, iade, e-posta, hukuk, KVKK, alan adı, SSL, stok, SEO/merchant onayları **ayrı** kabulden geçmeden canlıya çıkış yapılmaz.

**T10 — Güncelleme/geri dönüş:** Yedek al, checksum/SHA doğrula, migration çalıştır, webroot varlıklarını güncelle, HTTP duman testi yap; başarısızlık halinde önceki kod/veritabanı/dosyaları geri yükleme planı kontrol edilir.

## Sınırlar ve dürüstlük

Bu bir **kaynak kodu + CI denetimi**dir. Gerçek `baskafirma.com` alan adı oluşturulmadı; farklı bir hosting sağlayıcısında SSL/DNS/SMTP/PAYTR/kargo gerçek kabul testi yapılmadı. Otomatik testler siteyi sıfır hatalı garantilemez. Geliştirme dalındaki değişiklikler CI geçmeden ve ayrıca onaylanmadan hiçbir müşteriye yayınlanmamalıdır.

**Sonraki çalışma:** Bu rapor onaylandıktan sonra alan adı/hosting sağlayıcısı seçimine göre kullanıcıya eşlik eden ekran görüntülü, hata senaryolu, geri dönüş adımlı kurulum rehberi hazırlanacak ve rehber gerçek, yeni bir bağımsız domaine uygulanacak.
