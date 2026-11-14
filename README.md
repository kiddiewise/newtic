# NEWTIC — bağımsız Laravel e-ticaret altyapısı

**NEWTIC**, her müşteri için ayrı ve güvenli biçimde kurulabilen, mobil
öncelikli bir Laravel e-ticaret yazılımıdır. Kodun tek kalıcı kaynağı
GitHub main dalıdır. Her müşteri kendi domainine, MySQL veritabanına,
APP_KEY anahtarına, depolamasına ve yönetim bilgilerine sahip olur.

## Yeni mağaza kurma

[Bağımsız mağaza kurulum rehberi](docs/INSTALL_NEW_STORE.md)

Özet: Yeni bir domain/hosting ve ayrı veritabanı hazırlayın; main kodunu
public_html dışına alın; mağazaya özel .env oluşturun; önce salt-okunur
kontrolü, ardından kurulumu çalıştırın:

    bash scripts/install-production.sh --check
    bash scripts/install-production.sh --install
    php artisan newtic:admin

Laravel public dizini webroot olmalıdır. Hostinger planlarında
public_html zorunluysa boş bir webrootta
scripts/prepare-hostinger-webroot.sh kullanılabilir. Mevcut sitelerin
dosyaları hiçbir kurulum betiği tarafından otomatik silinmez.

## Marka bağımsızlığı

- Mağaza adı ve varsayılan marka: Admin > SEO Ayarları, ilk kurulumda APP_NAME.
- Logo: Admin > Logolar. Logosuz mağazada genel alışveriş simgesi ve mağaza adı.
- Ana sayfa, menü, kategoriler, ürünler, sosyal medya/WhatsApp, rozetler,
  SEO ve Google Merchant katalog metinleri her kurulumun kendi verilerinden gelir.
- Yeni bir mağazaya başka müşterinin WhatsApp numarası, ürünleri, logoları,
  API anahtarları ve sosyal medya adresleri kendiliğinden yüklenmez.
- Yeni kurulumda SEO noindex açıktır, canlıya çıkış kontrollerinden sonra
  admin tarafından kapatılabilir.

## Ürün iş kuralları

Her gramaj/SKU ayrı ürün, ayrı URL, ayrı fiyat ve stok olarak kalır.
Ürün detayında aynı ana kategorideki diğer ürünler bağımsız miktarlarla
sepete eklenebilir. Opsiyonlar ve yorumlar ürün bazında yönetilir.

## Güvenli ödeme ve kargo durumu

Bu aşamada **yeni domainlerde canlı PAYTR kart tahsilatı ve gerçek kargo
gönderisi bilerek etkin değildir**. İlgili modüller eski Polaris test
domaininde güvenli test moduna sınırlandırılmıştır. Yeni mağaza kurulumu,
gerçek ödeme/kargo izni anlamına gelmez. Havale/EFT yalnızca aktif banka
hesabı varsa görünür; ödeme bekleyen siparişlerin stok süresi kontrol edilir.

**E-posta altyapısı ve grafik kurulum sihirbazı bu aşamaya dahil değildir.**
Gerçek müşteri e-postaları için ayrıca geliştirme ve onay gereklidir.

## Tarihçe / önceki uygulama

İlk NEWTIC kurulumu Polaris Kurşun için hazırlanmış
Hostinger test mağazasıdır. Bu test dağıtımının yolları ve
özel güvenlik kapıları mevcut staging iş akışında korunur; yeni
müşteriler bunun yerine [genel kurulum rehberini](docs/INSTALL_NEW_STORE.md)
kullanmalıdır. Legacy ürün-demo seeder'ı üretim kurulumu için değildir.

[GitHub ve sürüm stratejisi](docs/BRANCH_AND_GO_LIVE.md)
