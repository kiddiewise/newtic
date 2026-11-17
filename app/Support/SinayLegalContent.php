<?php

namespace App\Support;

/**
 * Sinay Design specific legal content, based on Turkish consumer, commerce and
 * privacy rules reviewed 17 November 2026. Merchant facts were not invented.
 * Drafted for individual seller Sinem Durak; requires human legal verification
 * of contact/registration details and actual processing before live payments.
 *
 * Sources: Ticaret Bakanlığı Mesafeli Sözleşmeler rehberi (2026),
 * Mesafeli Sözleşmeler Yönetmeliği (24/05/2025 amendments, effective 2026),
 * 6502 sayılı Kanun, KVKK Aydınlatma ve Çerez rehberleri, 6563/İYS guidance.
 */
final class SinayLegalContent
{
    public const BRAND = 'Sinay Design';
    public const SELLER = 'Sinem Durak';
    public const DOMAIN = 'sinaydesign.com';

    public static function profile(): array
    {
        return [
            'legal_name' => self::SELLER,
            'registered_address' => null,
            'contact_email' => null,
            'contact_phone' => null,
            'tax_office' => null,
            'tax_number' => null,
            'mersis_number' => null,
            'kep_address' => null,
            'support_hours' => null,
            'show_paytr_disclosure' => false,
        ];
    }

    /** @return array<string,array{title:string,body:string}> */
    public static function pages(): array
    {
        return [
            'hakkimizda' => [
                'title' => 'Hakkımızda',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | HAKKIMIZDA

Sinay Design, Sinem Durak tarafından sunulan; kişiye anlam katan hediyelik eşyalar, karikatür temalı biblolar, dekoratif objeler, takılar ve aksesuarlar için oluşturulan bir çevrimiçi mağazadır.

Amacımız; ürünlerin ölçü, malzeme, renk, fiyat, hazırlık ve teslimat bilgilerini mümkün olduğunca açık sunmak, kişiye özel taleplerde alıcının beklentisi ile sipariş ayrıntılarını eşleştirmek ve satın alma sonrasında ulaşılabilir destek sağlamaktır. Her ürünün somut özellikleri ilgili ürün sayfasında belirtilir; örnek fotoğraflar ve ekran renkleri ile ürünün gerçek fiziksel özellikleri arasında görüntüleme koşullarından kaynaklanan farklılıklar olabileceği açıklanır.

KARİKATÜR BİBLOLAR VE KİŞİYE ÖZEL HEDİYELER
İsim, yazı, fotoğraf veya müşteri talimatı eklenebilen ürünlerde hangi alanların kişiselleştirildiği ürün sayfasında belirtilir. Siparişin içerdiği tasarım bilgileri sipariş sürecinde doğrulanır. Kişiselleştirmenin cayma hakkına etkisi, gerçekten müşterinin isteğine veya kişisel ihtiyaçlarına göre hazırlanan ürünler için ve kanuni sınırlar içinde değerlendirilir; ayıplı ürün hakları her koşulda saklıdır.

TAKI VE AKSESUARLAR
Ürünlerin ölçüsü, kullanılan temel malzemesi, bakım gereklilikleri, varsa kaplama veya hassasiyet uyarıları ilgili ürün sayfasında tanımlanır. Ürün adı veya görselinden hareketle altın, gümüş, değerli taş, el yapımı veya sertifikalı üretim iddiasında bulunulmaz; böyle bir özellik ancak doğrulandığında belirtilir.

SATICI VE ŞEFFAFLIK
Marka: Sinay Design
İşletmeci olarak bildirilen kişi: Sinem Durak
Web sitesi: https://sinaydesign.com

Satıcının resmî ticari kimliği ve kayıtlı açık adresi, e-posta, telefon, vergi/sicil bilgileri ve varsa KEP adresi doğrulandıktan sonra İletişim bölümünde yayımlanır. Bu alanların henüz tamamlanmamış olması, kanunen zorunlu bilgilendirmeden feragat anlamına gelmez; gerçek satışa geçmeden önce tamamlanmaları gerekir.
SINAY_TEXT,
            ],
            'iletisim' => [
                'title' => 'İletişim ve Satıcı Bilgileri',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | İLETİŞİM VE SATICI BİLGİLERİ

Marka: Sinay Design
İşletmeci olarak bildirilen kişi: Sinem Durak
Web sitesi: https://sinaydesign.com

Satıcıya ait hukuken geçerli ticari unvan, açık işletme ve tebligat adresi, doğrulanmış telefon ve müşteri hizmetleri e-posta adresi, uygulanabilir vergi/sicil ve KEP bilgileri bu sayfanın satıcı iletişim bölümünde ayrıca gösterilir. Mevcut kayıtlarda doğrulanmamış bir iletişim bilgisi uydurulmaz. Bu bilgiler eksikse satıcı, gerçek ödeme ve sipariş kabulünden önce bunları tamamlamak zorundadır.

HANGİ KONULARDA BAŞVURABİLİRSİNİZ?
Ürün içeriği, ölçü, kişiye özel karikatür veya biblo tasarım talimatları, sipariş değişikliği, sevkiyat, teslimat, cayma, ürün hasarı, yanlış gönderim, eksik parça, garanti/ayıplı mal ve kişisel veri talepleri için resmî iletişim kanallarını kullanabilirsiniz.

Sipariş numarası, ürün bilgisi ve sorunun kısa açıklaması talebin yönlendirilmesine yardımcı olur. Hasar veya farklılık halinde istenirse görsel gönderilebilir; yalnız fotoğraf/tutanak eksikliği tüketicinin kanuni ayıplı mal haklarını otomatik olarak ortadan kaldırmaz.

GÜVENLİK
Hiçbir görevli müşteriden mesaj yoluyla kart güvenlik kodu, tam kart numarası, banka şifresi, SMS doğrulama kodu veya hesap parolasını istemez. Şüpheli hesap ve ödeme durumlarını yalnızca mağazanın doğrulanmış iletişim kanallarından bildirin.

KVKK başvuruları ve tüketici uyuşmazlıklarına ilişkin yasal başvuru hakları, ilgili aydınlatma ve satış koşullarında açıklanır. Bu sayfa, satıcı profiline girilen doğrulanmış bilgilerle birlikte okunur.
SINAY_TEXT,
            ],
            'kvkk-aydinlatma' => [
                'title' => 'KVKK Aydınlatma Metni',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | KİŞİSEL VERİLERİN İŞLENMESİNE İLİŞKİN AYDINLATMA METNİ

1. VERİ SORUMLUSU
Sinay Design mağazası için veri sorumlusu olarak bildirilen işletmeci Sinem Durak'tır. Hukuken geçerli tam unvan, adres, telefon ve başvuru e-postası İletişim sayfasındaki doğrulanmış satıcı profilinden duyurulmalıdır. Bu aydınlatma 6698 sayılı Kişisel Verilerin Korunması Kanunu ("KVKK") kapsamındaki temel işlemleri açıklar.

2. KİŞİSEL VERİ KATEGORİLERİ
Ad, soyad, telefon, e-posta, teslimat ve fatura adresleri; müşteri hesap ve oturum bilgileri; ürün, tutar, ödeme sonucu ve sipariş geçmişi; destek talepleri ve yazışmaları; işlem güvenliği, IP, erişim kayıtları ve çerez tercihleri işlenebilir. Kişiye özel hediye veya karikatür biblo siparişinde ayrıca müşteri tarafından sağlanan isim, kutlama mesajı, fotoğraf, tasarım ayrıntısı ve ilgili üretim dosyaları işlenebilir. Özel nitelikli veri gönderilmesi amaçlanmaz.

3. AMAÇLAR VE HUKUKİ SEBEPLER
Siparişin oluşturulması, kişiselleştirmenin hazırlanması, teslimat ve müşteri hizmetleri KVKK m.5/2-c (sözleşmenin kurulması/ifası); fatura, muhasebe, vergisel işlemler ve yasal bildirimler m.5/2-ç (hukuki yükümlülük); uyuşmazlıkların çözümü ve hakkın korunması m.5/2-e; ölçülü güvenlik ve kötüye kullanım önlemleri koşulları varsa m.5/2-f kapsamında işlenebilir. Açık rızanın zorunlu olduğu reklam/analitik çerez veya tanıtım faaliyetleri ayrıca izin alınarak yürütülür. Aydınlatma metninin kabul edilmesi tek başına açık rıza sayılmaz.

4. TOPLAMA YÖNTEMLERİ
Veriler, sipariş ve üyelik formları, müşteri mesajları, yüklenen görseller, teknik loglar ve siparişin ifasında görevli hizmet sağlayıcılarla kurulan elektronik iletişim üzerinden elde edilebilir.

5. FOTOĞRAF VE ÜÇÜNCÜ KİŞİLER
Müşteri tarafından bir başkasına ait fotoğraf ya da kişisel bilgi sunulması durumunda, paylaşımı hukuka uygun şekilde yapma sorumluluğu müşteriye aittir. Satıcı da bu bilgileri yalnızca meşru sipariş amacı için sınırlı, ölçülü ve güvenli biçimde kullanmakla yükümlüdür. Yüklenen fotoğraflar ayrı bir hukuki sebep veya geçerli izin olmadan sosyal medyada, reklamda ya da portföyde yayımlanmaz.

6. ALICI GRUPLARI VE AKTARIM
İlgili veriler yalnız gerekli ölçüde ödeme kuruluşu veya bankaya, kargo/lojistik hizmetine, gerçekten kullanılan tasarım/üretim iş ortağına, barındırma ve iletişim hizmeti sağlayıcısına, muhasebe danışmanına ve hukuken yetkili kamu kurumlarına aktarılabilir. Bu açıklama herhangi belirli hizmet sağlayıcısının hâlen aktif olduğunu garanti etmez. Yurt dışına aktarım gerçekleşirse KVKK m.9 koşulları ayrıca sağlanır ve uygun aydınlatma yapılır.

7. SAKLAMA VE İMHA
Veriler ilgili mevzuatın zorunlu saklama süreleri, siparişin yerine getirilmesi ve makul hukuki ihtilaf süreleriyle sınırlı tutulur. Kişiye özel fotoğraf ve tasarım dosyaları için ayrı bir saklama-imha çizelgesi uygulanmalıdır; herhangi bir verinin süresiz saklanması amaçlanmaz.

8. İLGİLİ KİŞİNİN HAKLARI
KVKK m.11 uyarınca verinin işlenip işlenmediğini öğrenme; işleme hakkında bilgi isteme; amacına uygun kullanımı öğrenme; aktarım yapılan kişileri bilme; düzeltme, şartları varsa silme/yok etme ve bunların alıcılara bildirilmesini isteme; yalnız otomatik analizle aleyhe sonuç çıkmasına itiraz etme; kanuna aykırı işlem nedeniyle doğan zararın giderimini isteme haklarınız vardır.

9. BAŞVURU
Mevzuata uygun yazılı veya doğrulanmış elektronik kanallar üzerinden veri sorumlusuna başvuru yapılabilir. Gerekli hallerde kimlik doğrulaması istenebilir. Başvurular yasal süreler içinde, kural olarak en geç otuz gün içinde sonuçlandırılır. Doğrulanmış iletişim bilgileri satışa başlamadan önce tamamlanmalıdır.

10. GÜNCELLEME
Fiilen kullanılan analitik, pazarlama, bulut, e-posta veya üretim sağlayıcıları ve saklama politikaları değişirse metin yeniden gözden geçirilir. Bu metin fiilî veri envanteri incelemesinin yerini tutmaz.
SINAY_TEXT,
            ],
            'gizlilik-politikasi' => [
                'title' => 'Gizlilik Politikası',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | GİZLİLİK VE BİLGİ GÜVENLİĞİ

Sinay Design; ziyaretçi, üye ve sipariş sahiplerinin bilgilerinin korunmasını önemser. Bu politika, KVKK Aydınlatma Metniyle birlikte okunmalıdır.

Kişisel veriler yalnızca gerekli ve hukuka uygun amaçlarla toplanır. Ad, iletişim, adres, sipariş bilgileri, işlem referansı ve güvenlik kayıtları siparişin tamamlanması, destek ve yasal yükümlülüklerin yerine getirilmesinde kullanılabilir. Hesap parolaları açık metin saklanmamalı, ödeme kartlarının tam numaraları ve güvenlik kodları mağaza kayıtlarına alınmamalıdır.

KİŞİYE ÖZEL FOTOĞRAFLAR
Karikatür biblo veya kişiye özel hediye için gönderilen fotoğraf, isim ve mesajlar yalnızca sipariş tasarımını hazırlamak ve gereken destek/uyuşmazlık işlemlerini yürütmek amacıyla kullanılmalıdır. Bu görseller müşteri izni olmadan örnek iş, sosyal medya paylaşımı, tanıtım veya reklam materyali haline getirilmez. Üretim hizmeti üçüncü tarafa verildiğinde gerekli veriler sözleşme ve güvenlik güvenceleriyle sınırlandırılarak aktarılabilir.

ÜÇÜNCÜ TARAFLAR
Ödeme, kargo, e-posta, web barındırma ve meşru üretim desteği sağlayan taraflarla yalnız gerekli ölçüde veri paylaşılabilir. Bunlardan hangilerinin aktif olduğu mağazanın güncel uygulamalarına göre belirlenir. Veri sorumlusu üçüncü taraf hizmetlerini seçerken yeterli güvenlik ve hukukî aktarım şartlarını sağlamalıdır.

PAZARLAMA
Alışveriş yapmış olmak otomatik olarak reklam e-postası veya SMS'i için izin oluşturmaz. Ticari iletiler için gereken onaylar ayrı alınır; ret talepleri mevzuata uygun sürede uygulanır. Sipariş ve fatura gibi zorunlu işlem bildirimleri pazarlama ile karıştırılmaz.

ÇEREZLER
Zorunlu oturum, güvenlik ve sepet çerezleri ile isteğe bağlı analiz/reklam çerezleri farklıdır. Mevzuat gereği onay isteyen izleme teknolojileri olumlu tercih verilmeden yüklenmemelidir. Ayrıntılar Çerez Politikası ve tercih merkezinde bulunur.

VERİ SAKLAMA, GÜVENLİK VE HAKLAR
Yetkisiz erişim riskini azaltmak için makul idari ve teknik tedbirler uygulanır. Veriler amaç ve mevzuatta zorunlu sürelerle sınırlı tutulur. Silme, erişim veya düzeltme talepleri KVKK Aydınlatma Metni'nde belirtilen haklara tabidir. Başvuru için İletişim sayfasındaki doğrulanmış satıcı kanalları kullanılmalıdır.

Bu politika belirli bir güvenlik sertifikasının veya hiç veri ihlali yaşanmayacağının garantisi değildir.
SINAY_TEXT,
            ],
            'cerez-politikasi' => [
                'title' => 'Çerez Politikası ve Tercihler',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | ÇEREZ POLİTİKASI

1. ÇEREZ NEDİR?
Çerezler, web sitesini ziyaret ettiğinizde cihazınızda saklanabilen küçük tanımlayıcı veya tercih dosyalarıdır. Benzeri yerel teknolojiler de bu politika kapsamına girebilir.

2. ZORUNLU ÇEREZLER
Oturum, sepet, güvenlik kontrolleri, ödeme aşaması güvenliği, dil/görünüm ve çerez tercihlerinin hatırlanması için gerekli çerezler kullanılabilir. Hizmetin kullanıcı tarafından açıkça istenen temel işlevleri için kesinlikle gerekli olduğu ölçüde ayrıca açık rıza aranmayabilir. Bu istisna reklam ve kapsamlı ziyaretçi profillemesine genişletilemez.

3. ANALİTİK VE PERFORMANS
İsteğe bağlı ziyaretçi ölçümü için çerez/benzer tanımlayıcılar kullanılacaksa işleme faaliyetinin hukuki şartları ayrıca değerlendirilir. Açık rıza gereken analitik teknolojiler kullanıcı kabul etmeden çalıştırılmamalıdır.

4. REKLAM VE SOSYAL MEDYA
Reklam pikselleri, davranışsal hedefleme ve üçüncü taraf sosyal ağ tanımlayıcıları yalnız gerekli hukuki dayanak ve tercih mekanizması sağlandığında yüklenebilir. Bu politika söz konusu teknolojilerin mutlaka aktif olduğunu iddia etmez.

5. ÇEREZ TERCİHİNİZ
Sitedeki “Çerez tercihleri” alanından kategorilere göre seçim yapabilir, ilgili kontrolü reddedebilir veya verdiğiniz izni geri çekebilirsiniz. Tarayıcı ayarlarından da çerez yönetimi yapabilirsiniz; zorunlu çerezlerin engellenmesi sepet veya oturum özelliklerini etkileyebilir. Kabul ve ret seçenekleri yanıltıcı biçimde sunulmamalıdır.

6. TEKNİK ENVANTER
Çerezlerin adı, sağlayıcısı, ilk/üçüncü taraf durumu, amacı ve saklama süresi fiilen yüklenen yazılım ve servisler üzerinden düzenli kontrol edilmelidir. Bu politikada doğrulanmamış üçüncü taraf çerez adı veya süre uydurulmamaktadır. Canlı satıştan önce gerçek çerez envanteri ile tercih panelinin eşleştiği doğrulanmalıdır.

7. HAKLAR VE İLETİŞİM
Çerezler aracılığıyla kişisel veri işlendiği ölçüde KVKK kapsamındaki haklarınız ve başvuru usulleri KVKK Aydınlatma Metni'nde açıklanır. Satıcının doğrulanmış iletişim kanalları İletişim sayfasındadır.
SINAY_TEXT,
            ],
            'teslimat-ve-iade' => [
                'title' => 'Teslimat, Cayma ve İade Koşulları',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | TESLİMAT, CAYMA VE İADE KOŞULLARI

1. ÜRÜNÜN NİTELİĞİ
Sinay Design'da standart hediyelik eşyalar, dekoratif biblolar, takı ve aksesuarlar ile müşterinin fotoğraf, isim, metin veya özel talimatlarına göre hazırlanan kişiye özel ürünler satılabilir. Bir ürünün hangi sınıfa girdiği siparişten önce ürün sayfasında açıkça gösterilmelidir.

2. HAZIRLAMA VE TESLİMAT
Ürüne özel hazırlık süresi ve seçilen teslimat şekli sipariş sırasında açıklanır. Her ürüne aynı gün üretim veya teslimat taahhüt edilmez. Kişiye özel siparişlerde gerekli dosyaların eksik, uygunsuz veya işlenemeyecek nitelikte olması durumunda müşteriye bilgi verilir. Mal, tüketiciye vaat edilen sürede; kanunen uygulanabilir durumlarda ise her hâlükârda 30 günü aşmayacak şekilde teslim edilir. Kişiye özel hazırlanacak mallar bakımından mevzuattaki farklılık saklıdır. İfanın imkânsızlaşması halinde tüketici mevzuata uygun sürede bilgilendirilerek gerekli bedel iadesi yapılır.

3. STANDART ÜRÜNLERDE 14 GÜNLÜK CAYMA
Yasal istisna bulunmayan standart ürünlerde tüketici, malı kendisinin veya belirlediği üçüncü kişinin teslim aldığı günden itibaren 14 gün içinde gerekçe göstermeden ve cezai şart ödemeden cayma hakkına sahiptir. Süre içinde açık bir cayma bildiriminin satıcıya yöneltilmesi yeterlidir. Bildirim yazılı veya kalıcı veri saklayıcısı üzerinden iletilir.

4. KİŞİYE ÖZEL HAZIRLANAN ÜRÜNLER
Mesafeli Sözleşmeler Yönetmeliği m.15/1-b uyarınca tüketicinin istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan ürünlere ilişkin sözleşmelerde mevzuattaki cayma hakkı istisnası uygulanabilir. Bu istisna ürünün yalnızca “hediye”, “biblo”, “takı” veya “siparişe özel” adıyla satılmasına değil, gerçekten kişisel ihtiyaçlara göre hazırlanmasına bağlıdır. Kişiselleştirme olmayan hazır tasarımlarda genel cayma kuralları geçerlidir. Ayıplı, hatalı hazırlanmış, yanlış gönderilmiş veya hasarlı ürünlere ilişkin kanuni haklar hiçbir durumda ortadan kalkmaz.

5. İADE KARGO MASRAFI
Cayma hakkının kullanılabildiği ürünlerde, satıcının sipariş ön bilgilendirmesinde belirttiği taşıyıcı ile iade yapıldığında tüketiciye iade kargo masrafı yüklenmez. Satıcı uygun taşıyıcıyı veya ücretsiz iade kanalını açıkça bildirmelidir. Taşıyıcının müşterinin bulunduğu yerde şubesi yoksa mevzuata uygun alternatif/teslim alma sağlanır. Sipariş sırasında doğrulanmamış bir taşıyıcı adı veya ücret uydurulmaz.

6. İADE SÜRECİ VE BEDEL
Cayma bildirimi alındıktan sonra tüketici mevzuattaki 14 günlük süre içinde ürünü iade etmek üzere göndermelidir; satıcının kendisinin teslim almayı teklif etmesi halinde buna göre hareket edilir. Satıcı, geçerli mevzuat çerçevesinde tahsil ettiği tutarı ve gerekli teslimat masraflarını en geç 14 gün içinde, kullanılan ödeme aracıyla uyumlu şekilde ve tüketiciye ek maliyet doğurmadan iade eder. Bankanın hesaba yansıtma süresi satıcının kanuni yükümlülüğünü değiştirmez.

7. AYIPLI ÜRÜN, KIRILMA VE YANLIŞ TASARIM
Kırık, hasarlı, eksik, siparişte onaylanan içerikten farklı veya ayıplı ürünlerde 6502 sayılı Kanun'dan doğan seçimlik haklar geçerlidir. Satıcı; onarım, ayıpsız misliyle değiştirme, ayıp oranında indirim veya sözleşmeden dönme taleplerini kanuni koşullara göre değerlendirir. Kargo tesliminde tutanak veya fotoğraf düzenlenmesi incelemeyi kolaylaştırabilir ancak tek başına tüketici hakkının şartı değildir.

8. GÖRSEL VE RENK FARKLILIKLARI
Ekran ayarlarından doğan küçük görüntü farklılıkları olabilir; bu durum ürünün açıklanan malzeme, ebat, renk veya önemli özelliklerine aykırı gönderilmesini meşrulaştırmaz. Kişiselleştirme sonucu siparişte onaylanan tasarım kriterlerine uygun olmalıdır.

9. İADE ADRESİ VE İLETİŞİM
İadelerin gönderileceği açık adres, doğrulanmış satıcı profilinden ve siparişe ilişkin ön bilgilendirmeden öğrenilir. Satıcının kimlik/iletişim bilgileri eksikse canlı satış öncesinde tamamlanmalıdır.

10. UYUŞMAZLIKLAR
Tüketicinin Tüketici Hakem Heyetleri ve görevli tüketici mahkemelerine başvuru hakları saklıdır. 2026 yılı için hakem heyeti parasal sınırı 186.000 TL'nin altındaki uyuşmazlıklardır; sonraki yıllarda güncel resmî sınır uygulanır.
SINAY_TEXT,
            ],
            'mesafeli-satis-sozlesmesi' => [
                'title' => 'Mesafeli Satış Sözleşmesi',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | MESAFELİ SATIŞ SÖZLEŞMESİ

1. TARAFLAR
SATICI: Sinay Design markasının işletmecisi olarak bildirilen Sinem Durak. Hukuken geçerli tam ticari unvan, açık adres, vergi/sicil bilgileri, telefon ve e-posta satıcının doğrulanmış İletişim/Satıcı Profili bölümünden, ayrıca sipariş tamamlanmadan önce sunulan ön bilgilendirmeden belirlenir. Bu veriler eksikken mevzuata uygun gerçek satış işlemi tamamlanmamalıdır.
ALICI: Sipariş sırasında kimliği ve teslimat/fatura bilgileri belirtilen tüketicidir.
İNTERNET SİTESİ: https://sinaydesign.com.

2. SÖZLEŞMENİN KONUSU
Alıcının elektronik ortamda sipariş verdiği, ürün sayfası ve sipariş özetiyle nitelikleri, satış bedeli ve teslimat detayları açıklanan hediyelik, karikatür biblo, dekoratif obje, takı veya aksesuarların satışı ve teslimidir. Sözleşme, 6502 sayılı Kanun ve yürürlükteki Mesafeli Sözleşmeler Yönetmeliği esas alınarak uygulanır.

3. SİPARİŞE ÖZGÜ ESASLAR
Ürün adı, adedi, malzeme/ölçü, kişiselleştirme tercihleri, birim ve toplam fiyat, vergiler, kargo ve varsa ilave masraflar, ödeme yöntemi, teslimat adresi ve taahhüt edilen hazırlık/teslim süresi, alıcıya ödeme yükümlülüğü doğmadan önce gösterilen sipariş özeti ve ön bilgilendirme ile belirlenir. Sipariş anındaki bu kayıtların saklanması ve tüketiciye kalıcı veri saklayıcısı yoluyla iletilmesi esastır. Genel metin tek başına siparişe özgü bedel ve taşıyıcı bilgisinin yerine geçmez.

4. SÖZLEŞMENİN KURULMASI
Alıcı, sipariş toplam bedelini ve ödeme yükümlülüğünü açıkça görerek siparişi onaylar. Ödeme ve sözleşme işlem adımları platformdaki güvenli yöntemlerle tamamlanır. Ödeme doğrulaması ile siparişin kabulü ve durumu ayrıca bildirilir. Satıcı, doğrulanmamış ödeme için tahsilat yapılmış gibi davranmaz.

5. TESLİMAT
Mal sipariş sırasında bildirilen süreye uygun biçimde teslim edilir. Kanuni koşulların uygulanabildiği satışlarda teslimat süresi 30 günü geçmez; tüketicinin istekleri veya kişisel ihtiyaçları doğrultusunda hazırlanan mallar için ilgili istisna saklıdır. Teslimin imkânsızlaşması halinde kanuni bildirim ve iade yükümlülükleri uygulanır.

6. CAYMA HAKKI
Yasal istisna bulunmayan mal satışlarında alıcı, teslimden itibaren 14 gün içinde herhangi bir gerekçe göstermeden cayabilir. Bu hak için süresi içinde satıcının doğrulanmış iletişim kanalına veya sunulan cayma/başvuru sistemine açık irade beyanı iletilir. Bildirimin yasal sürede yapılması yeterlidir. Kanunen gerekli ön bilgilendirmenin yapılmamasının hukuki sonuçları saklıdır.

7. KİŞİSELLEŞTİRİLMİŞ ÜRÜNLER
Tüketicinin fotoğraf, isim, mesaj veya özgün tasarım talimatıyla gerçekten kişisel ihtiyacına göre hazırlanan ürünlerde Mesafeli Sözleşmeler Yönetmeliği m.15/1-b gereği cayma hakkı istisnası söz konusu olabilir. Sırf ürünün “hediyelik” veya “sipariş üzerine” olarak adlandırılması istisna oluşturmaz. İstisna, ilgili ürün için satış öncesinde açıkça bildirilir. Hatalı kişiselleştirme, ayıplı imalat, eksik/yanlış teslim ve kanuni seçimlik haklar bu istisna ile sınırlandırılmaz.

8. İADE VE BEDELİN GERİ ÖDENMESİ
Cayma hakkının kullanılabildiği hallerde, alıcı ürünü mevzuatın belirlediği süre içinde satıcıya veya yetkilendirdiği kişiye iade eder. Satıcının ön bilgilendirmede belirttiği taşıyıcıyla yapılan iadeler bakımından iade kargo masrafları tüketiciye yüklenmez. Satıcı, gerekli tutarı mevzuattaki en geç 14 günlük süreye ve usule uygun iade eder. Kanunen tüketiciye ait olmayan bir iade masrafı mahsup edilemez.

9. AYIPLI MAL
Alıcı, 6502 sayılı Kanun'un ayıplı mala ilişkin hükümleri uyarınca ücretsiz onarım, ayıpsız misli ile değiştirme, indirim ve sözleşmeden dönme dahil kanuni seçimlik haklara sahiptir. Bu hükümler kişiselleştirilmiş ürünler için de uygulanır.

10. KİŞİSEL VERİ VE GÖRSELLER
Kişiye özel ürün dosyaları siparişin ifası için gerekli ölçüde işlenir. Fotoğrafların reklam, sosyal medya veya portföy paylaşımı ayrıca geçerli hukuki sebep olmaksızın yapılmaz. KVKK Aydınlatma Metni ve Gizlilik Politikası uygulanır.

11. UYUŞMAZLIK VE YÜRÜRLÜK
Uyuşmazlıklarda Türkiye Cumhuriyeti tüketici mevzuatı uygulanır. Kanunen yetkili Tüketici Hakem Heyetleri ve tüketici mahkemelerine başvuru hakları saklıdır. Bu metindeki tüketici aleyhine olabilecek veya zorunlu mevzuata aykırı hükümler uygulanmaz; kanuni korumalar önceliklidir. Sözleşmenin sipariş anındaki sürümü ve ön bilgilendirmesi saklanır.
SINAY_TEXT,
            ],
            'on-bilgilendirme-formu' => [
                'title' => 'Mesafeli Satış Ön Bilgilendirme Formu',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | SİPARİŞ ÖNCESİ ÖN BİLGİLENDİRME

Bu metin 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamında sipariş kurulmadan önce gerekli bilgilere ilişkin genel çerçeveyi açıklar. Nihai ön bilgilendirme; alıcının somut siparişindeki ürünleri, fiyatları ve teslimat koşullarını ayrıca içermelidir.

1. SATICI BİLGİLERİ
Satışa sunan marka: Sinay Design.
İşletmeci olarak bildirilen kişi: Sinem Durak.
Web sitesi: https://sinaydesign.com.
Satıcının hukuken geçerli tam unvanı, açık adresi, telefon ve e-posta adresi siparişten önce doğrulanmış satıcı profili üzerinden gösterilmelidir. Bu alanlar tamamlanmadan gerçek satış başlatılmamalıdır.

2. MALIN TEMEL NİTELİKLERİ
Ürünün adı, adet ve çeşidi, ölçüsü, malzemesi, renk/kaplama özellikleri, bakım uyarıları ve varsa isim, fotoğraf, karikatür veya mesaj kişiselleştirmesi her ürün sayfasında/sipariş özetinde belirtilir. Ürün yalnızca hazır satılıyorsa kişiye özel cayma istisnası uygulanmaz.

3. TOPLAM BEDEL
KDV dâhil satış fiyatı, teslimat/kargo tutarı, diğer zorunlu masraflar, kupon/indirimler ve ödenecek nihai tutar ödeme yükümlülüğü doğmadan önce açıkça gösterilir. İlan edilmeyen ek bedeller alıcıya yüklenemez.

4. ÖDEME VE TESLİMAT
Seçilen ödeme yöntemi, teslimat adresi, taşıyıcı, tahmini kargoya verme ve teslim süresi sipariş özelinde belirtilir. Kişiye özel ürünlerde üretim/teslim takvimi ürün bazında ayrıca açıklanır; standart ürünler için vaat edilmeyen hızlı teslimat iddiasında bulunulmaz.

5. CAYMA HAKKI
Kanuni istisna olmayan mal satışlarında tüketicinin teslimden itibaren 14 günlük gerekçesiz cayma hakkı vardır. Bildirim yazılı veya kalıcı veri saklayıcısı yoluyla satıcının doğrulanmış kanalına iletilir. Cayma hakkı olan ürünler için iadenin yapılacağı taşıyıcı ve masraf düzeni sipariş öncesinde somutlaştırılmalıdır. Satıcının belirttiği taşıyıcıyla iade kargo masrafı tüketiciye yüklenmez.

6. KİŞİYE ÖZEL ÜRÜN İSTİSNASI
Gerçekten müşterinin fotoğrafı, ismi, özel mesajı veya kişisel ihtiyacına göre üretilen mal, Yönetmelik m.15/1-b kapsamına giriyorsa gerekçesiz cayma hakkı bulunmayabilir. İstisna ürün bazında açıkça işaretlenmelidir. Ayıplı, eksik, yanlış veya siparişe aykırı ürünlerde tüketicinin tüm kanuni hakları devam eder.

7. İADE VE ŞİKÂYET
Cayma bildirimi ve ürün iadesi yürürlükteki mevzuatın süre ve usullerine tabidir. Hasarlı veya ayıplı ürüne ilişkin seçimlik haklar saklıdır. Siparişe ait doğrulanmış satıcı iletişim bilgileri ve iade kanalı tüketiciye kalıcı veri saklayıcısıyla iletilmelidir.

8. TİCARİ ELEKTRONİK İLETİ VE VERİ
Bu formun kabulü reklam/pazarlama izni vermez. Kişisel veriler KVKK Aydınlatma Metni'ne göre işlenir. Müşteri fotoğraflarının tanıtımda paylaşılması sipariş için zorunlu değildir ve ayrıca uygun hukuki sebep gerektirir.

9. HAK ARAMA YOLLARI
Tüketici, kanunen yetkili tüketici hakem heyetleri ve mahkemelerde talepte bulunabilir. 2026'da 186.000 TL altındaki uyuşmazlıklar bakımından hakem heyeti parasal sınırı uygulanır; güncel yıllık sınırlar ayrıca kontrol edilmelidir.

ÖNEMLİ: Bu genel metin, sipariş ekranında gösterilmesi gereken gerçek ürün, ad-soyad, satıcı adresi, vergi, ödeme, taşıyıcı, teslim tarihi ve tutar verilerinin yerine geçmez. NEWTIC'in sipariş anındaki özet ve sürüm kaydıyla birlikte uygulanır.
SINAY_TEXT,
            ],
            'uyelik-sozlesmesi' => [
                'title' => 'Üyelik Koşulları',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | ÜYELİK KOŞULLARI

1. TARAFLAR VE KAPSAM
Bu üyelik koşulları, https://sinaydesign.com adresindeki çevrimiçi mağazanın işletmecisi olarak bildirilen Sinem Durak (Sinay Design) ile platforma kayıt olan kullanıcı arasındaki üyelik hizmetine ilişkindir. Satıcıya ait doğrulanmış iletişim ve ticari bilgiler İletişim sayfasında yer almalıdır.

2. ÜYELİK VE DOĞRU BİLGİ
Kullanıcı kayıt sırasında ilettiği bilgilerin doğru ve güncel olmasını sağlamalı, kendisine ait olmayan hesaplarla izinsiz işlem yapmamalıdır. Üyelik, satın alma taahhüdü doğurmaz. Üye olmadan alışveriş imkânı varsa bunun şartları sipariş akışında gösterilir.

3. GÜVENLİK VE HESAP
Kullanıcı parolasını veya giriş doğrulama kodlarını paylaşmamalıdır. Şüpheli erişim, sahtecilik ya da hizmetin güvenliğini tehdit eden faaliyetlerde hukuka uygun ve ölçülü tedbirler alınabilir. Bu tedbirler tüketicinin tamamlanmış satın almalarından doğan haklarını kaldırmaz.

4. ÜRÜNLER VE SİPARİŞ
Üyelik kapsamında görüntülenen fiyat, malzeme, renk, ölçü, stok ve teslimat bilgileri ilgili ürün/sipariş ekranında yer alır. Kişiye özel üretimlerde fotoğraf, yazı veya isim talimatları ancak sipariş kapsamında değerlendirilir. Alışverişler Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu'na tabidir.

5. FOTOĞRAF, TASARIM VE TELİF
Kullanıcı yüklediği fotoğraf, isim, metin ve tasarım öğelerini ilgili siparişi gerçekleştirmek amacıyla sağlamaya yetkili olmalıdır. Başkalarının telif, marka, kişilik veya mahremiyet haklarını ihlal eden talepler hukuka uygun şekilde reddedilebilir. Kullanıcı, siparişe ilişkin sınırlı işleme dışında görsellerinin reklamda kullanılmasına peşinen izin vermiş sayılmaz.

6. KİŞİSEL VERİLER VE REKLAM
Üyelik verileri KVKK Aydınlatma Metni'ndeki hukuki sebeplere göre işlenir. Ticari elektronik ileti onayı üyelikten ayrı ve isteğe bağlıdır. Üyelik sözleşmesinin kabulü kampanya e-postası/SMS izni oluşturmaz.

7. ÜYELİĞİN SONA ERMESİ
Kullanıcı hesabının kapatılmasını talep edebilir; sipariş, vergi, muhasebe ve diğer kanuni zorunluluklar nedeniyle saklanması gereken kayıtlar ilgili süreler boyunca korunabilir. Hesabın kapatılması kazanılmış tüketici haklarını, teslimat ve iade taleplerini ortadan kaldırmaz.

8. DEĞİŞİKLİK VE UYUŞMAZLIKLAR
Bu metin mevzuata uygun şekilde güncellenebilir. Önceden kurulmuş satışlar aleyhe geriye dönük değiştirilmez. Uyuşmazlıklarda tüketicinin kanunen yetkili mercilere başvuru hakları saklıdır.
SINAY_TEXT,
            ],
            'islem-rehberi' => [
                'title' => 'Alışveriş ve İşlem Rehberi',
                'body' => <<<'SINAY_TEXT'
SİNAY DESIGN | ELEKTRONİK TİCARET İŞLEM REHBERİ

1. ÜRÜNÜ BULUN
Sinay Design'da hediyelik eşyalar, dekoratif biblolar, karikatür temalı figürler, takılar ve aksesuarlar arasından ürün seçebilirsiniz. Ürün adı, fiyatı, malzemesi, temel ebatları ve varsa kişiselleştirme açıklamasını inceleyin. Ürün stok ve satış durumu seçiminizi etkileyebilir.

2. KİŞİYE ÖZEL TALİMATLAR
Fotoğraf, isim, kısa mesaj, görsel veya tasarım talimatı isteyen ürünlerde istenen alanları doldurun. Görselin paylaşılmasına yetkiniz olduğundan emin olun. Ürün sayfasında belirtilen dosya türü, kalite ve üretim sınırlarına uyun. Kişiye özel siparişte yazım hataları ve fotoğraf seçimini ödeme öncesinde kontrol edin.

3. SEPET VE FİYAT KONTROLÜ
Sepette adet, birim fiyat, indirim, varsa kargo ve KDV dâhil ödenecek toplamı gözden geçirin. Yanlış ürün veya miktar varsa ödeme yapmadan önce değiştirin. Stok, fiyat veya sevkiyat bilgileri güncellenmişse sipariş onayında güncel bilgiler esas alınır.

4. HESAP / TESLİMAT BİLGİLERİ
Varsa üyeliğinize giriş yapın veya izin verilen misafir alışverişiyle devam edin. Alıcının adı, iletişim, teslimat ve fatura bilgilerini doğru girin. Yanlış adres bildirimi gecikme yaratabilir; destek kanalına mümkün olan en kısa sürede haber verin.

5. ÖN BİLGİLENDİRME VE SÖZLEŞME
Ödeme yükümlülüğü doğmadan önce satıcının doğrulanmış kimliği/iletişimi, ürün özellikleri, toplam fiyat, teslimat, cayma hakkı ve kişiye özel ürün istisnaları açıkça sunulur. Mesafeli Satış Sözleşmesi ve Ön Bilgilendirme Formu'nu okuyup uygun onayı verin. Ticari reklam izni ayrı bir tercihtir.

6. ÖDEME VE SİPARİŞ
Sunulan ödeme yöntemi ile işlemi tamamlayın. Siparişin alındığına dair onay, sipariş referansı ve varsa ödeme durumunu saklayın. Ödeme sonucu kesinleşmeden ödeme yapılmış gibi değerlendirilmez. Sözleşme ve ön bilgilendirme kaydı kalıcı veri saklayıcısı üzerinden paylaşılmalıdır.

7. ÜRETİM, KARGO VE TAKİP
Siparişe özel bildirilen hazırlık süresi ile gönderi aşamalarını hesabınızdan veya destek kanallarından takip edin. Kişiye özel üretimlerde gereken bilgiler eksikse satıcı müşteriyle iletişime geçebilir. Kullanılan kargo şirketi ve teslimat tahmini sipariş bazında açıklanır.

8. CAYMA, İADE VE AYIPLI ÜRÜN
Standart mallarda ilgili koşullarla 14 günlük cayma hakkı vardır. Gerçekten kişiye özel hazırlanmış mallarda yasal istisna olabilir; bunun ürün bazında açıkça bildirilmesi gerekir. Ayıplı veya yanlış ürün şikâyetlerinde kanuni haklar devam eder. Cayma veya ayıp bildirimini doğrulanmış destek kanalından iletebilirsiniz.

9. KAYITLARA ERİŞİM VE HATALARI DÜZELTME
Sözleşmenin sipariş anındaki sürümü ve sipariş özeti işletme kayıtlarında tutulur. Kullanıcılar, hesaplarında sunulan sipariş geçmişinden işlem detaylarını inceleyebilir; erişim sorunu varsa destek talep edebilir. Hesap bilgisi, sipariş ve fatura düzeltmeleri uygulanabilir mevzuata göre değerlendirilir.

10. BAŞVURU VE UYUŞMAZLIK
İletişim sayfasındaki doğrulanmış kanallar kullanılır. Tüketicinin Tüketici Hakem Heyetlerine ve yetkili mahkemelere başvuru hakkı saklıdır. İşbu rehber, siparişe özgü zorunlu ön bilgilendirmeyi veya sözleşmeyi değiştirmez.
SINAY_TEXT,
            ],
        ];
    }
}
