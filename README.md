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

Hostinger'de Node.js/npm yoksa GitHub Actions'ın aynı main commit için
ürettiği newtic-install-assets paketiyle kurulum yapılabilir:

    bash scripts/install-production.sh --install --assets-bundle "$PWD/newtic-install-assets.tar.gz"

Hostinger boş public_html kullanıyorsa CSS/JS, sabit görseller ve yüklemeler
gerçek fiziksel dizinlere yayımlanır; sunucunun takip edemediği static
symlink'lere güvenilmez:

    bash scripts/prepare-hostinger-webroot.sh --webroot "$HOME/domains/MUSTERI-DOMAINI/public_html"
    bash scripts/verify-public-assets.sh
    php artisan newtic:setup-token

Son komut kısa ömürlü kurulum kodu verir. Tarayıcıda ilgili domainin
/install adresini açarak mağaza adı ve ilk yöneticiyi oluşturun.
Bilgisayarsız kurulum için --private-file seçeneği vardır.
Mevcut web sitesi dosyaları hiçbir ilk kurulum betiği tarafından silinmez.

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

Polaris Kurşun için kullanılan gramaj/SKU bazlı ayrı ürün ve kategori içinden
çoklu ürün seçme davranışı mevcut mağazada korunur. Bu iş kuralı **yeni her
mağazaya zorunlu gramaj modeli olarak uygulanmaz**. Diğer müşterilerin
ürün ve kategori verileri kendi kurulumlarının veritabanında oluşturulur;
sektöre özel ürün UX özellikleri ayrı geliştirilir.

## Güvenli ödeme ve kargo durumu

Bu aşamada **yeni domainlerde canlı PAYTR kart tahsilatı ve gerçek kargo
gönderisi bilerek etkin değildir**. İlgili modüller eski Polaris test
domaininde güvenli test moduna sınırlandırılmıştır. Yeni mağaza kurulumu,
gerçek ödeme/kargo izni anlamına gelmez. Havale/EFT yalnızca aktif banka
hesabı varsa görünür; ödeme bekleyen siparişlerin stok süresi kontrol edilir.

İlk mağaza için güvenli tarayıcı kurulum sihirbazı ve isteğe bağlı
SMTP/e-posta yönetimi hazırdır. Gerçek müşteri bildirimleri, toplu kampanya
gönderimleri ve canlı ödeme/kargo, her mağazada ayrı onay ve test gerektirir.

## Tarihçe / önceki uygulama

İlk NEWTIC kurulumu Polaris Kurşun için hazırlanmış
Hostinger test mağazasıdır. Bu test dağıtımının yolları ve
özel güvenlik kapıları mevcut staging iş akışında korunur; yeni
müşteriler bunun yerine [genel kurulum rehberini](docs/INSTALL_NEW_STORE.md)
kullanmalıdır. Legacy ürün-demo seeder'ı üretim kurulumu için değildir.

[GitHub ve sürüm stratejisi](docs/BRANCH_AND_GO_LIVE.md)
