# NEWTIC — Canlı satışa geçiş ve ticari ürünleştirme planı

**Plan tarihi:** 23 Kasım 2026  
**Kaynak:** `kiddiewise/newtic`, ana sürüm `2ebcd90f3f96b4f39f0523f993bc2ca106a1b286`.  
**Kapsam:** Polaris NEWTIC önizleme, Sinay Design ve farklı müşterilere ayrı NEWTIC kurulumu.  
**Karar:** Test akışları çalışıyor; gerçek kart tahsilatı, gerçek taşıyıcı, zamanlayıcı, yedek kurtarma ve WooCommerce eski sipariş arşivi tamamlanana kadar koşulsuz canlı satış onayı verilmez.

## Doğrulanmış başlangıç durumu

- Polaris: 126 ürün, 26 kategori, 39 test siparişi, 0 gerçek sipariş; PAYTR test callback ve Dummy Kargo akışları çalışıyor. Sinay: 1 test siparişi, bağımsız mağaza ve veritabanı.
- Polaris görsel depolaması: 526 görsel, 85,46 MB; 486 WebP, 35 JPG, 5 PNG. 16 dosya 512 KiB ve 4 dosya 1 MiB üzerinde.
- Etkin derleme: ana CSS 385,1 KiB / gzip yaklaşık 74,7 KiB; ana JS 75,3 KiB / gzip yaklaşık 22 KiB. Birikmiş eski sürümlerin toplamı gerçek sayfa indirme boyutu değildir.
- SMTP: Polaris testte yalnızca güvenli deneme alıcısına iletiyor; Sinay SMTP kapalı. Hosting hesabında SSH `crontab` aracı yok; uygulamada 1 dakikalık scheduler sağlık kaydı bulunmuyor.
- Polaris yedek sağlık kaydı doğrulanmamış; Sinay'da önceki korumalı dağıtım yedeği var. **Bu, otomatik, şifreli ve sunucu dışı yedek/geri yükleme prosedürünün çalıştığını ispatlamaz.**
- PAYTR testinin geçerli callback kontrolü var, ancak `PaytrTestAccess` belirli staging hostuna bağlı; gerçek tahsilat başka domaine otomatik açılmaz. Kargo da `ShippingTestAccess` ve Dummy Kargo ile sınırlı.
- Composer kilit dosyası bilinen güvenlik bildirimi: 0. npm üretim çözümlemesi geçici lock ile yapılan denetimde bildirim: 0. Bu sonuçlar bağımsız sızma testi yerine geçmez.
- Public HTTPS çalışıyor. Gerçek yönetici giriş yanıtında Secure/HttpOnly çerezleri gözlendi. CDN üzerinden görülen ilave HTTP güvenlik başlıkları tümüyle tamamlanmamıştı.

## Aşamalar ve kabul kriterleri

| Aşama | Hedef | Çıkış koşulu | Veri/ödeme riski |
| --- | --- | --- | --- |
| 1 — Güvenli temel | Üretim güvenlik başlıkları, özel sipariş sayfasında `no-store`, cron, bağımsız yedek ve geri yükleme | Testler yeşil; hPanel cron gerçek heartbeat üretiyor; otomatik DB+dosya yedeği ve **ayrı ortamda geri yükleme provası** doğrulanmış; kimlik bilgisi/log sızıntısı yok | Üretim koduna yalnızca testli ve geri alınabilir sertleştirme; gerçek ödeme açılmaz |
| 2 — Canlı PAYTR | Mağaza/domain bağımsız, satıcıya ait test/canlı ayarları ve sunucu callback'i | Gerçek mağaza hesabı onayı; doğru domain; tutar/imza/idempotence; başarısız ödeme, tekrar deneme, timeout, iade ve mutabakat testi | Canlı anahtarlar korumalı; önizlemede gerçek tahsilat kesinlikle kapalı |
| 3 — Gerçek kargo | Dummy dışı taşıyıcılar için üretim durum makinesi, barkod ve kayıtlar | Sağlayıcı onayı; gerçek etiket; çift gönderi engeli; geç/tekrarlanan callback; kayıp durum eşitleme; iptal/iade senaryoları | Sadece onaylı taşıyıcıda etkin; ödeme doğrulanmadan etiket çıkmaz |
| 4 — WooCommerce geçmişi | HPOS/legacy kaynak tespiti; salt okunur eski sipariş arşivi ve etiketli veri eşleme | Eski ve yeni satır sayıları, kaynak ID, müşteri, kalem, tutar, vergi, indirim, iade, kargo ve belge sayımı birebir mutabık | Mevcut WooCommerce **asla yazılmaz/silinmez**. Arşiv geçmişinden yeniden tahsilat, stok veya kargo tetiklenmez |
| 5 — Hız, SEO ve UX | Responsive görsel türevleri, ölü asset temizliği, Core Web Vitals ve 301 planı | Mobil tarayıcıda LCP/INP/CLS ölçümü, kırık görsel yok; URL eşleme; yasal sayfalar ve noindex geçişi kontrollü | Medya asılları korunur; eski URL'lerin kaybı engellenir |
| 6 — Ticari kabul | Müşteri bazlı ilk kurulum, sürüm güncelleme, backup/rollback, rapor | Gerçek müşteri alan adıyla uçtan uca satın alma → e-posta → kargo → iade testi, 2 rol, OWASP ASVS tabanlı kontrol; operasyon sahibi tarafından onay | Müşteri mağazaları birbirinden ayrı DB/APP_KEY ve sağlayıcı hesabı kullanır |

## Aşama 1'in küçük, geri alınabilir kod değişikliği

- `HardenHttpResponses`: `nosniff`, `X-Frame-Options: DENY`, `Permissions-Policy`, referrer azaltımı; giriş, sepet, ödeme, yönetim ve imzalı sipariş sayfalarında `private, no-store`.
- Var olan CDN'nin CSP politikasına ve PAYTR iFrame'ine müdahale edilmez. Yönetici girişi/kayıt, checkout ve callback korunur.
- HTTPS/HSTS politikası nihai müşteri domaininde DNS/CDN doğrulaması sonrasında ayrı etkinleştirilir; staging Hostinger alt alanında gelişigüzel HSTS zorlanmaz.
- hPanel `Cron Jobs` üzerinden **her dakika** `php /absolute/path/artisan schedule:run` çalıştırılmalı. GitHub zamanlayıcı yedeği, dakikalık sipariş e-postası teslimatı için eşdeğer değildir.
- Yedekleme: uygulama `health:backup-report` komutu yalnızca **başarılı ve doğrulanmış gerçek yedek sonrasında** çalıştırılır; komut kendiliğinden yedek oluşturmaz.
- Cookie Secure gerçek TLS yanıtında gözlendiği için bu aşamada üretim oturum ayarları değiştirilmez; nihai domain kurulumu test edilir.

## Eski WooCommerce siparişleri — zorunlu iş kuralları

1. Eski WooCommerce veritabanı/medya/fatura ekleri önce şifreli ve doğrulanabilir biçimde saklanır. Eski sistem okuma dışında kullanılmaz.
2. REST API/HPOS veya legacy tablo yapısı tespit edilir. Sipariş ID'si ve kaynak mağaza kimliği biriciktir; tekrar import **idempotent** olacaktır.
3. Yeni `legacy_orders` benzeri ayrı arşiv tabloları tanımlanır; canlı NEWTIC `orders` ve `paytr_payment_attempts` tablolarına geçmiş sipariş sokulmaz.
4. Sipariş durumları, kısmi iade, çoklu kargo, taksit, kargo vergisi, kupon ve PDF/fatura referansları kayıt altına alınır. İstisnalar raporlanır; desteklenmeyen alanlar sessizce atılmaz.
5. Ön aktarım → artımlı aktarım → eski mağazanın kontrollü checkout dondurulması → son farkların aktarımı → toplam/adet kontrolü → alan adı devri. Eski site erişilebilir korumalı arşiv olarak korunur.
6. Müşteri hesapları Laravel parolasına kopyalanmaz; e-posta doğrulama/parola sıfırlama yöntemi uygulanır. E-posta/telefon eşleşmesi tek başına eski sipariş görüntüleme hakkı vermez.

## Yayın ve geri dönüş protokolü

- Kod PR + tam otomatik test + kod gözden geçirme olmadan `main` içine girmez.
- Sır, şifre, PAYTR anahtarı, müşteri verisi, eski sipariş export'u, otomatik yedek GitHub depoya konmaz.
- Polaris WooCommerce canlı domainine bu plan kapsamında işlem yapılmaz. Sinay bağımsız veritabanı/ayarları korunur.
- Her yayın öncesinde doğrulanabilir yedek, sürüm SHA, olası DB migration etkisi ve geri alma yolu belirlenir.
- Bir aşamanın tamamlanması, diğer aşamayı otomatik olarak canlıya açmaz. Canlı ödeme ve gerçek taşıyıcı, ayrı test ve onay kapılarından geçer.

## Müşteri için teslim kararı

**Bugün:** Demo / müşteri kurulumu hazırlığı / kontrollü pilot.  
**Henüz değil:** Onaysız gerçek kart tahsilatı; gerçek taşıyıcı gönderimi; WooCommerce eski siparişleri aktarılmadan Polaris domain geçişi; garantili yüksek güvenlik iddiası.

Sıradaki operasyonel iş: Hostinger hPanel dakikalık Cron Jobs ve şifreli dış yedek/restore kanıtı; ardından PAYTR canlı geçitlerinin ayrı geliştirme PR'ı.
