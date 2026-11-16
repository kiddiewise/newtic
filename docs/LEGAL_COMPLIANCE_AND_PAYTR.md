# NEWTIC – E-ticaret hukuki sayfalar, PAYTR ve güvenilir alışveriş denetimi

**Kapsam:** NEWTIC'in bütün mağazalarındaki ortak altyapı. Yalnız Polaris'e,
Sinay Design'a veya başka tek bir müşteriye özel değildir.

**Durum:** Yazılım, satıcı kimliği ve yasal dokümanların mağaza bazında
yönetimini sağlar. Metinler **boş taslak** olarak başlar. Kendi şirket
bilgilerine ve ürün grubuna göre hukuk danışmanı tarafından incelenip
yayınlanmadan hukuki uyum veya PAYTR onayı iddia edilemez.

**Resmi kaynaklar (16 Kasım 2026 itibarıyla):**
- PAYTR başvuru şartları: https://www.paytr.com/destek-merkezi/basvuru
- PAYTR çerçeve ödeme hizmeti sözleşmesi (logo ve ödeme açıklaması):
  https://www.paytr.com/on-bilgilendirme
- Ticaret Bakanlığı mesafeli sözleşmeler bilgi rehberi (17 Ağustos 2026):
  https://tuketici.ticaret.gov.tr/yayinlar/tuketici-bilgi-rehberi/mesafeli-sozlesmeler-hakkinda-bilgilendirme
- Ticaret Bakanlığı e-ticaret sıkça sorulan sorular:
  https://ticaret.gov.tr/ic-ticaret/sikca-sorulan-sorular/elektronik-ticaret
- KVKK çerez rehberi: https://www.kvkk.gov.tr/Icerik/7353/Cerez-Uygulamalari-Hakkinda-Rehber
- KVKK aydınlatma ve rıza ayrılığı:
  https://www.kvkk.gov.tr/Icerik/4132/aydinlatma-yukumlulugunun-yerine-getirilmesinde-uyulacak-usul-ve-esaslar-hakkinda-teblig
- Ticari elektronik ileti/IYS:
  https://ticaret.gov.tr/ic-ticaret/ticari-elektronik-iletiler/ileti-yonetim-sistemi-iys

## PAYTR için gerekli kamusal sayfalar

PAYTR başvuru koşulları, mağazada aktif olarak şu bölümleri istiyor:

1. Hakkımızda
2. İletişim
3. Gizlilik Politikası
4. Mesafeli Satış Sözleşmesi
5. Teslimat ve İade Şartları

Bunların yanında Türkiye'de e-ticaretin mevzuat gereklilikleri nedeniyle:
6. Ön Bilgilendirme Formu / ödeme öncesi açıklama
7. KVKK Aydınlatma Metni
8. Çerez Politikası ve gerekiyorsa izin yönetimi
9. Üyelik Koşulları (üyelik sunuluyorsa)
10. İşlem Rehberi ve teknik sipariş aşamaları

NEWTIC admin /admin/yasal-sayfalar üzerinden her sayfa için başlık, düz metin,
yayın durumu ve sürüm takip eder. Yayımlanmayan sayfalar 404 döner; footer
onlara bağlantı vermez. Bu yüzden **canlı yayına çıkmadan** içeriğin
yayınlanması gerekir. Başkasının gizlilik/mesafeli satış metnini
kopyalamayın. HTML içeriği yürütülmez, XSS amacıyla metne eklenen script
ziyaretçilere escaped olarak basılır.

## Satıcı kimliği ve iletişim

Ticaret Bakanlığının kendi sitesinden e-ticaret yapanlar için
iletişim konusunda saydığı bilgilerden (şirketin türüne göre) bazıları:
- Tam ticari unvan, açık adres, telefon, ulaşılabilir e-posta
- Tebligata elverişli KEP adresi
- Tacir için MERSİS; esnaf için ilgili vergi/kimlik bilgileri
- İlgili oda/kuruluş bilgileri ve sektörel kurallar, uygun oldukça

Yeni yönetim paneli şirket adı, adres, telefon, e-posta, KEP, MERSİS,
vergi dairesi/numarası ve çalışma saatlerini mağazaya özel saklar.
TCKN gibi gereksiz hassas kişisel bilgileri otomatik olarak footer'a basmaz.

## Üyelik

- Giriş/üye ol formunda KVKK aydınlatmasına erişim (aydınlatma bir hak ve
  yükümlülüktür, otomatik açık rıza gerektirmez).
- Üyelik koşulları yayınlıysa zorunlu ama ayrı checkbox; imzalı
  metin sürümü ve metnin kendisi kullanıcı kaydına bağlanır.
- Pazarlama e-posta izni **önceden seçili değildir**, üyelik şartı değildir.
- Kampanya/IYS kayıtları ayrı kontrol edilir; onaysız müşteriye kampanya
  e-postası gönderilmez.
- İlk kez Google OAuth ile üye olanlara, varsa yayınlanmış üyelik koşulları
  ayrı sayfada sunulur. Onayla birlikte tam metin/sürüm kullanıcıya kaydedilir;
  mevcut Google hesabının tekrar girişinde ilave onay istenmez. KVKK
  aydınlatması ayrıca görülebilir. Pazarlama onayına zorlanmaz.

## Sepet ve ödeme onayı

- Satıcı, ürün nitelikleri, toplam fiyat, vergiler, teslimat masrafı,
  cayma, iade ve uyuşmazlık başvuru yolları hakkında satın alma öncesi
  bilgilendirme sağlamalıdır.
- Müşteri sipariş vermeden önce sepeti, adetleri, adresi ve toplamı
  düzenleyebilmelidir.
- Ödeme yükümlülüğü açıkça belirtilmelidir.
- NEWTIC, mağazaya özel yayınlanmış **Ön Bilgilendirme Formu +
  Mesafeli Satış Sözleşmesi** varsa sipariş formunda bunlara bağlantı
  ve zorunlu onay gösterir.
- Onaydan sonra kaydedilen her siparişte sözleşmelerin tam içerik kopyası,
  sürüm, SHA-256, satıcı kimliği, tarih, fiyat, kargo, toplam, döviz kuru değil
  para birimi ve ürün/adet/opsiyon anlık görüntüsü yer alır.
- Belgeler değiştiğinde eski checkout revizyon kodu ile sipariş vermek
  reddedilir. Geri dönülüp yeni metin incelenmelidir.
- Metinler hazır değilken TEST modu geriye dönük test siparişlerine
  izin verebilir. CANLI ödeme/sipariş için yayın ve satıcı kimliği
  tamamlanmalıdır.

**ÖNEMLİ SINIR:** Siparişin yasal metnini yalnızca genel sayfa şeklinde
tutmak her sektör için yeterli değildir; dinamik teslim tarihi, iade kargo
taşıyıcısı, ürün grubu istisnası ve kişiye özel üretim bilgileri ayrıca
sipariş bazında ön bilgiye eklenmelidir. Bu PR sipariş verilerini bir kanıt
olarak kaydeder, ancak hukuki tamlık garanti etmez.

## İade ve özel üretim

Ticaret Bakanlığının 2026 rehberine göre tüketici kural olarak malı
teslim aldıktan itibaren 14 gün içinde cayabilir; istisnalar bulunur.
Kişisel ihtiyaç doğrultusunda özel üretilmiş ürünler bakımından cayma
hakkı istisnası söz konusu olabilir. Bu istisna **bütün ürünler için
otomatik varsayılmamalıdır**. Yeni ürün sistemine ürün bazında
"kişiye özel üretim" durumu ve ilgili bilgilendirme eklendiğinde
ön bilgilendirme/teslimat metni de buna göre düzenlenmelidir.

## Cookie/KVKK

- Gerekli oturum, sepet, CSRF koruması çerezleri çalışır.
- Varsayılan olarak optional analytics/marketing kategori izinleri false.
- Ziyaretçiye eşit düzeyde Kabul / Reddet / Tercihler sunulur.
- Tercih browser localStorage içinde 180 gün tutulur, footer'dan
  yeniden düzenlenebilir.
- Uygulama varsayılanında optional analitik/reklam script'i bulunmaz;
  ileride entegrasyon yapan geliştirici tercihi kontrol etmeden script
  eklememelidir. Cookie banner varlığı tek başına teknik KVKK uyumu
  sağlamaz; gerçek script/cookie taraması ve onay kanıtı ayrıca gerekir.
- Google Fonts gibi dış bağlantıların veri aktarımı, çerez banner'ı
  dışında ayrıca gözden geçirilmeli ve gerekirse yerel font kullanılmalı.

## Ödeme logoları, PAYTR ve güven damgaları

- PAYTR'nin yayımlanan çerçeve sözleşmesi, aktif üye işyeri için
  PAYTR logosu/bağlantısı ve ödeme hizmeti duyurusu şartlarını içerir.
  Logo görselleri sağlayıcının izni ve yönergelerine uygun alınmalıdır.
- VISA, Mastercard, TROY gibi kart şemalarını yalnızca gerçekten
  etkin/uygun ödeme hizmeti varsa gösterin.
- TR GO Güven Damgası, ETID veya sektör derneği logoları **bir güven
  veya üyelik iddiasıdır**. Gerçek tescil/üyelik olmadan footer'da
  görünmemelidir. Varsayılan kurulum bunları otomatik eklemez.
- Uygulama footer'ı yalnız açıkça işaretlenmiş aktif PAYTR hesabı ve
  gerçek canlı kart ödemesi söz konusuysa ödeme kurumunu ve metinsel
  kart markalarını gösterir. Marka logolarının görselleri bu sürüme
  dahil edilmedi; onaylı resmi görsel yükleme ve yetki/doğrulama
  sonraki geliştirme konusudur.
- Mobil uygulama mağaza butonları yalnızca gerçek yayınlanmış iOS/
  Android uygulaması ve aktif mağaza linki varsa gösterilmelidir.
- Yönetim panelinde doğrulanmış kullanım hakkı onayıyla ödeme kuruluşu,
  güven/üyelik işareti ve mobil uygulama görselleri yüklenebilir.
  Güven/üyelik işareti ile uygulama bağlantısında resmi HTTPS doğrulama
  adresi zorunludur. Bu adresin gerçekliğini yazılım otomatik tescil
  sorgulamasıyla doğrulamaz; sorumluluk mağaza yönetimine aittir.
  Ödeme logo görselleri ancak gerçekten etkin canlı kart altyapısı varsa
  footer'da görüntülenir. Varsayılan mağazada hiçbir güven işareti yoktur.

## İşletme dışı işlemler

- ETBIS kaydı, gerektiğinde MERSIS/ESBIS/KEP ve İYS veri bildirimi,
  teknik yazılımla tamamlanmış sayılmaz; işletmenin veya yetkilinin
  ilgili resmî kaydı ayrıca yapması gerekir.
- PAYTR sözleşme/mağaza yetkisi, IBAN, SSL, güvenlik politikaları
  ayrıca sağlayıcı tarafından doğrulanmalıdır.
- SPF/DKIM/DMARC, veri saklama ve silme planı, KVKK veri aktarımı
  (örn. Google/Facebook/CDN/analitik), tüketici şikayet/iade kanalı,
  sözleşme kopyasının kalıcı saklanması, ürün bazlı cayma istisnaları
  ve kanuni sürelerin hukuk danışmanıyla gözden geçirilmesi gerekir.

## Devreye alma sırası

1. Önce admin satıcı bilgilerini doldur.
2. Hukuk danışmanı ile 10 belgeyi mağazaya özel oluşturup yayımla.
3. Footer'daki iletişim ve politikalara erişimi mobil/desktop test et.
4. Üyelik ve checkout onaylarını (metin sürümü, değişiklik, zorunluluk)
   test et.
5. Ürün bazında teslimat/cayma istisnası ve gerçek iade talebi işlem
   akışlarını işletmenin ihtiyacına göre tamamla.
6. PAYTR müşteri temsilcisinin mağaza şartlarını kontrol etmesini iste.
7. Gerçek banka/kargo/kart işlemlerini sadece test ve açık onayla etkinleştir.
8. ETBIS, KVKK, IYS ve güven damgası doğrulamaları bitince indekslemeyi aç.

Bu belge genel mühendislik/uyumluluk envanteridir; hukuki danışmanlık
veya bütün işletmelere aynı şekilde uygulanabilir bir sözleşme değildir.
