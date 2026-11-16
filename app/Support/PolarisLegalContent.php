<?php

namespace App\Support;

/**
 * Merchant-specific, human-readable drafts adapted from the existing
 * public polariskursun.com footer on 16 November 2026.
 *
 * These pages are NOT generic NEWTIC defaults; only a guarded explicit
 * Polaris staging import is permitted. No third-party legal templates.
 *
 * Sources:
 * https://polariskursun.com/hakkimizda/
 * https://polariskursun.com/iletisim/
 * https://polariskursun.com/gizlilik-politikasi/
 * https://polariskursun.com/kisisel-verilere-iliskin-aydinlatma-metni/
 * https://polariskursun.com/uyelik-sozlesmesi/
 * https://polariskursun.com/sartlar-ve-kosullar/
 * https://polariskursun.com/iptal-ve-iade-kosullari/
 * https://polariskursun.com/sikca-sorulan-sorular/
 */
final class PolarisLegalContent
{
    public const BRAND = 'Polaris Kurşun';
    public const SELLER = 'Zoka Balık Av Malzemeleri Mücahit Meydan';
    public const ADDRESS = 'Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir';
    public const EMAIL = 'bilgi@polariskursun.com';
    public const PHONE = '0546 262 90 02';

    public static function profile(): array
    {
        return [
            'legal_name' => self::SELLER,
            'registered_address' => self::ADDRESS,
            'contact_email' => self::EMAIL,
            'contact_phone' => self::PHONE,
            // Verified official identifiers and operating hours were not
            // available from the source. Never invent them.
            'kep_address' => null,
            'mersis_number' => null,
            'tax_office' => null,
            'tax_number' => null,
            'support_hours' => null,
            'show_paytr_disclosure' => false,
        ];
    }

    /**
     * Source-backed adaptations requiring merchant/legal review, not assertions that
     * the operator has completed ETBIS, IYS, trust-seal or PAYTR approvals.
     *
     * @return array<string, array{title:string,body:string}>
     */
    public static function pages(): array
    {
        return [
            'hakkimizda' => [
                'title' => 'Hakkımızda',
                'body' => <<<'TEXT'
Polaris Kurşun Hakkında

Polaris Kurşun, Zoka Balık Av Malzemeleri'nin balık avcılığı ekipmanları ve surf kurşunları alanında faaliyet gösteren markasıdır. Zoka Balık Av Malzemeleri 2014 yılında İzmir'de balık av malzemelerinin satış ve satış sonrası hizmetlerini sunmak amacıyla faaliyetlerine başlamıştır.

Amatör ve profesyonel balıkçıların ihtiyaçlarına odaklanan işletme, 2021 yılında kaplamalı surf kurşunlarının üretimine de başlamıştır. Ürün ailesinde renkli, fosforlu ve kaplamasız surf kurşunları; klasik ve fırdöndülü modeller, aparatlı seçenekler ve olta bedenleri yer alır. Kaplama altına gramaj bilgisinin yerleştirilmesi ve farklı renk/ışıldama seçenekleri ürün geliştirme çalışmalarımızın bir parçasıdır.

İşletme geçmişte İzmir Konak/Üçyol'daki mağazasında perakende satış yapmış; Şubat 2026'dan itibaren satışlarını ağırlıklı olarak internet üzerinden sürdürmeye geçmiştir.

Hedefimiz, ürün özellikleri ve gramajların açıkça görüldüğü, kolay sipariş verilebilen, teslimat ve iade koşulları anlaşılır bir alışveriş deneyimi sunmaktır. Ürünlerin güncel stok bilgileri, satış fiyatları ve teknik özellikleri ilgili ürün sayfalarında gösterilir.

Marka: Polaris Kurşun
İşletme: Zoka Balık Av Malzemeleri Mücahit Meydan
İnternet sitesi: https://polariskursun.com
İletişim: bilgi@polariskursun.com · 0546 262 90 02
TEXT,
            ],
            'iletisim' => [
                'title' => 'İletişim',
                'body' => <<<'TEXT'
Polaris Kurşun İletişim Bilgileri

Ürünler, siparişinizin durumu, teslimat, iade veya kişisel veri başvurularınız hakkında bizimle iletişime geçebilirsiniz.

Marka: Polaris Kurşun
Satıcı / işletme: Zoka Balık Av Malzemeleri Mücahit Meydan
Adres: Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir
Telefon: 0546 262 90 02
E-posta: bilgi@polariskursun.com
Web sitesi: https://polariskursun.com

Siparişle ilgili bir konuda yazıyorsanız, sipariş numaranızı belirtmeniz işleminizi kolaylaştırır. Açık ve kullanılabilir iletişim bilgileriniz dışında parola, kart numarası veya kart güvenlik kodu göndermeyiniz.

Kişisel verilerinizle ilgili taleplerinizi de yukarıdaki e-posta veya diğer yasal başvuru kanalları aracılığıyla iletebilirsiniz. Mesajınızın konusu ve dayanağına göre ilgili yasal süreler içinde yanıt verilir.

Mağazanın faaliyet adresi ve teslimat için kullanılan iade adresi aynı adreste gösterilmektedir. İade göndermeden önce İptal ve İade Şartları sayfamızı inceleyiniz.
TEXT,
            ],
            'gizlilik-politikasi' => [
                'title' => 'Gizlilik Politikası',
                'body' => <<<'TEXT'
Polaris Kurşun Gizlilik ve Bilgi Güvenliği Politikası

1. Kapsam
Bu politika, Polaris Kurşun'un NEWTIC altyapısıyla sunulan çevrimiçi mağazasında kişisel bilgilerin hangi işlemler için kullanılabileceğini ve koruma yaklaşımını açıklar. Veri sorumlusu, Zoka Balık Av Malzemeleri Mücahit Meydan'dır. KVKK Aydınlatma Metni kişisel veri işlemenin ayrıntılarını ayrıca belirtir.

2. Alınabilecek bilgiler
Hesap açılması ve sipariş işlemleri sırasında ad-soyad, iletişim bilgileri, teslimat/fatura adresi, siparişin ürün ve ödeme durumu, müşteri talepleri ve gerekli işlem güvenliği kayıtları işlenebilir. Oturum, sepet ve sistem güvenliği için teknik kayıtlar tutulabilir.

3. Kullanım amaçları
Bilgiler; hesabın işletilmesi, siparişlerin alınması, ödemenin doğrulanması, faturanın düzenlenmesi, ürünlerin hazırlanması ve taşınması, iade/destek taleplerinin değerlendirilmesi, yasal yükümlülüklerin yerine getirilmesi ve sistem güvenliğinin sağlanması amacıyla kullanılabilir.

4. Ödeme güvenliği
Kartlı ödeme etkinleştirildiğinde ödeme ekranı PAYTR entegrasyonu üzerinden sağlanır. Mağaza uygulaması kart numarası ve kart güvenlik kodunu kendi veritabanında saklamak üzere tasarlanmamıştır. Yalnızca ödeme durumu, işlem referansı gibi siparişin takibi için gereken kayıtlar saklanabilir. Kart işlemi sunan üçüncü tarafın kendi güvenlik ve gizlilik koşulları da geçerlidir.

5. Veri paylaşımı
Siparişin ifası için gerekli ölçüde kargo hizmet sağlayıcıları, ödeme kuruluşu, barındırma ve e-posta hizmet sağlayıcıları, mali müşavir ve yetkili mercilerle veri paylaşımı söz konusu olabilir. Paylaşım her durumda geçerli mevzuat ve ilgili işleme şartlarına tabidir.

6. Pazarlama ve iletişim tercihleri
Kampanya e-postaları ve diğer ticari elektronik iletiler, ayrı izin ve gerekli İYS kontrolleri tamamlanmadan yalnız alışveriş yapıldığı için gönderilmez. Pazarlama tercihi, üyelik ve satış sözleşmesini onaylamanın şartı değildir. Verilen izin ilgili kanallardan geri alınabilir.

7. Güvenlik
İletişim TLS/HTTPS ile korunur. Kullanıcıların parolalarını üçüncü kişilerle paylaşmaması ve şüpheli işlemleri destek adresine bildirmesi tavsiye edilir. Hiçbir internet sisteminin mutlak güvenlik garantisi bulunmadığı unutulmamalıdır.

8. Haklar ve güncellemeler
6698 sayılı Kanun kapsamındaki başvurular için bilgi@polariskursun.com adresi kullanılabilir. İşleme amaçları ve haklarla ilgili ayrıntılar KVKK Aydınlatma Metni'nde açıklanır. Politika güncellendiğinde yeni sürüm bu sayfada yayımlanır.

Veri sorumlusu: Zoka Balık Av Malzemeleri Mücahit Meydan
İletişim: bilgi@polariskursun.com · 0546 262 90 02
TEXT,
            ],
            'kvkk-aydinlatma' => [
                'title' => 'KVKK Aydınlatma Metni',
                'body' => <<<'TEXT'
Kişisel Verilerin İşlenmesine İlişkin Aydınlatma Metni

1. Veri sorumlusu
6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında veri sorumlusu Zoka Balık Av Malzemeleri Mücahit Meydan (Polaris Kurşun) olarak belirtilmektedir.
Adres: Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir
E-posta: bilgi@polariskursun.com
Telefon: 0546 262 90 02

2. İşlenebilen kişisel veriler
İşletmemizle kurduğunuz ilişkiye bağlı olarak kimlik ve iletişim bilgileriniz, teslimat/fatura adresiniz, ürün/sipariş/ödeme durumu kayıtlarınız, müşteri hizmetleri yazışmalarınız, üyelik ve güvenlik bilgileriniz, teknik erişim kayıtlarınız ve izin tercihleriniz işlenebilir. Ödeme için kart bilgilerinin saklanması mağaza uygulamasının amacı değildir.

3. İşleme amaçları
Üyelik hesabının oluşturulması ve işletilmesi; ürün satışının ve teslimatın gerçekleştirilmesi; sipariş, banka havalesi veya ödeme durumunun yönetilmesi; fatura ve muhasebe işlemleri; cayma, iade, şikâyet ve müşteri taleplerinin karşılanması; bilgi güvenliği, dolandırıcılığın önlenmesi ve hukuki yükümlülüklerin yerine getirilmesi amaçlarıyla gerekli veriler işlenebilir.

4. Veri toplama yöntemi
Bilgiler; web sitesi formları, üyelik ve ödeme akışları, destek talepleri, otomatik sistem kayıtları ve ilgili üçüncü taraf hizmet sağlayıcıları aracılığıyla elektronik ortamda veya yetkili iletişim kanallarında toplanabilir.

5. Hukuki dayanaklar
Verilerin işlenmesi, işleme türüne göre KVKK'nın 5. maddesinde yer alan sözleşmenin kurulması/ifası, hukuki yükümlülüğün yerine getirilmesi, bir hakkın tesisi/kullanılması/korunması ve koşulları varsa meşru menfaat hukuki sebeplerine dayanabilir. Açık rıza gerektiren işlemler için ayrıca ilgili kişinin özgür iradesiyle verdiği açık rıza aranır.

6. Alıcılar ve aktarım
Siparişin gerçekleşmesi için gereken bilgiler ödeme kuruluşlarına, kargo ve lojistik sağlayıcılarına, uygulama/barındırma ve e-posta hizmeti tedarikçilerine, muhasebe ve yetkili kamu kurumlarına gerekli ölçüde aktarılabilir. Yurt dışına veri aktarımı söz konusu olduğunda yürürlükteki KVKK koşullarına uygun hareket edilmesi gerekir. Bağlı üçüncü taraf hizmetleri ve veri aktarımı uygulamaları ayrıca güncel tutulmalıdır.

7. Saklama ve imha
Veriler, işleme amacının gerektirdiği süre ve mevzuatta öngörülen saklama yükümlülükleri çerçevesinde muhafaza edilir; hukuki gerekçe sona erdiğinde uygulanabilir usule göre silinir, yok edilir veya anonim hâle getirilir.

8. İlgili kişinin hakları
KVKK'nın 11. maddesi uyarınca kişisel verinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi isteme, amacına uygun kullanımını öğrenme, aktarıldığı üçüncü kişileri bilme, eksik veya yanlış verileri düzelttirme, şartları varsa sildirme/yok ettirme ve hukuka aykırı işlemeden doğan zararınızın giderilmesini isteme haklarınız bulunmaktadır.

9. Başvuru
Başvurularınızı bilgi@polariskursun.com adresinden başlatabilir; mevzuatta belirtilen diğer usullerle veri sorumlusuna iletebilirsiniz. Kimlik doğrulaması gereken başvurularda ilave bilgi talep edilebilir. Pazarlama izinlerini geri çekmek için ayrıca üyelik tercihleri ve ileti abonelik iptal kanalları kullanılabilir.

Bu metin alışveriş ve hesap işlemlerine ilişkin genel aydınlatmadır. Belirli çerezler, veri aktarımı veya üçüncü taraf araçların kullanımı değişirse güncellenmelidir.
TEXT,
            ],
            'uyelik-sozlesmesi' => [
                'title' => 'Üyelik Koşulları',
                'body' => <<<'TEXT'
Polaris Kurşun Üyelik Koşulları

1. Taraflar ve hizmet
Polaris Kurşun çevrimiçi mağazası, Zoka Balık Av Malzemeleri Mücahit Meydan tarafından işletilir. Üyelik, mağazadaki ürünleri inceleme, sipariş oluşturma, adres ve sipariş geçmişine erişme gibi özellikleri kullanmak için bir hesap açılmasını sağlar.

2. Üyelik bilgilerinin doğruluğu
Üye, hesabını oluştururken ilettiği bilgilerin doğru ve güncel olmasından, hesabındaki değişiklikleri zamanında bildirmekten ve parolasını korumaktan sorumludur. Hukuka aykırı, başkalarının haklarına zarar veren veya platform güvenliğini tehlikeye düşüren kullanım yasaktır.

3. Hesap güvenliği
Şüpheli bir erişim fark edildiğinde destek kanallarına başvurulabilir. Sistem güvenliğini tehdit eden veya hukuka aykırı olduğu değerlendirilen hesap faaliyetleri gerekli ve ölçülü önlemlerle sınırlandırılabilir; tüketicinin kanuni hakları saklıdır.

4. Fiyatlar ve sipariş
Üyelik oluşturulması satın alma zorunluluğu doğurmaz. Ürünlerin fiyatları, stok ve gramajları ayrı ürün sayfalarında belirtilir. Bir satış işlemi yalnızca sipariş adımları tamamlanıp ödeme yükümlülüğü açıkça onaylandığında ilgili satış koşullarına tabidir.

5. Kişisel veriler ve pazarlama
Hesap oluşturulurken gerekli kişisel verilerin işlenmesi KVKK Aydınlatma Metni'nde açıklanır. Kampanya ve ticari elektronik ileti izni, üyelik koşullarından ve zorunlu işlem bildirimlerinden ayrı tutulur; isteğe bağlıdır. Bu koşulların kabulü tek başına pazarlama izni anlamına gelmez.

6. Üyeliğin sona ermesi
Üye hesabının kapatılmasını talep edebilir. Sipariş, fatura ve hukuki yükümlülükler nedeniyle saklanması gereken kayıtlar geçerli saklama süresince korunabilir. Üyeliğin sona ermesi tamamlanmış satışlardan doğan kanuni hakları ortadan kaldırmaz.

7. Değişiklikler ve iletişim
Metin değiştiğinde yeni sürüm yayımlanır. Yeni üyelik sırasında geçerli sürümün kaydı tutulabilir. İşletmeye bilgi@polariskursun.com veya 0546 262 90 02 üzerinden ulaşılabilir.
TEXT,
            ],
            'mesafeli-satis-sozlesmesi' => [
                'title' => 'Mesafeli Satış Sözleşmesi',
                'body' => <<<'TEXT'
POLARİS KURŞUN – MESAFELİ SATIŞ SÖZLEŞMESİ

1. Taraflar
Satıcı: Zoka Balık Av Malzemeleri Mücahit Meydan (Polaris Kurşun)
Adres: Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir
Telefon: 0546 262 90 02
E-posta: bilgi@polariskursun.com

Alıcı, sipariş sırasında adını, iletişim bilgilerini ve teslimat/fatura adresini bildiren kişidir. Alıcıya ilişkin sipariş anındaki bilgiler, sipariş kaydında yer alır.

2. Konu
Bu sözleşme, alıcının Polaris Kurşun'un çevrimiçi mağazasında seçip sipariş ettiği ürünlerin satışı ve teslimatı ile tarafların 6502 sayılı Tüketicinin Korunması Hakkında Kanun ve Mesafeli Sözleşmeler Yönetmeliği kapsamındaki hak ve yükümlülükleri hakkındadır.

3. Ürün, fiyat ve sipariş bilgileri
Ürünlerin cinsi, marka/modeli, gramajı, seçilen diğer özellikleri ve adetleri ürün detayında ve ödeme öncesi sipariş özetinde gösterilir. Seçilen her gramaj ayrı ürün olabilir. Vergiler dâhil fiyatlar, varsa indirimler, taşıma bedeli, toplam tutar ve ödeme yöntemi checkout ekranında alıcıya sunulur. Sipariş anında kabul edilen ürün/adet/tutarlar sipariş kaydıyla ilişkilendirilir.

4. Siparişin kurulması ve ödeme
Alıcı, ödeme yükümlülüğü doğuran siparişi onaylamadan önce bilgilerini kontrol edebilir. Uygun olan ödeme yöntemleri checkout ekranında sunulur. Kartlı ödeme hizmeti etkinse ilgili ödeme kuruluşunun güvenli ödeme ekranı kullanılır; havale/EFT seçeneğinde sipariş ekranında gösterilen banka bilgileri ve sipariş numarası esas alınır.

5. Teslimat
Ürünler, sipariş sırasında bildirilen adrese gönderilir. Hazırlık ve sevkiyat süresi stok ve üretim durumuna bağlıdır; mevcut mağaza bilgilendirmesine göre yoğunlukta kargoya verme süresi 3–4 iş gününe uzayabilir. Teslimat için belirtilen tahmini süre ve mevzuattaki azami süreler saklıdır. Teslimatın imkânsızlaşması veya gecikmesi durumunda uygulanacak yasal haklar korunur.

6. Cayma hakkı
Tüketici, uygulanabilir yasal istisnalar dışında, ürünü teslim aldığı tarihten itibaren 14 gün içinde gerekçe göstermeden cayma hakkını kullanabilir. Cayma bildirimi iletişim kanallarından biri aracılığıyla yöneltilir. İadeye konu ürünün durumu ve geri gönderilmesi konusunda güncel Teslimat ve İade Şartları bilgilendirmesi geçerlidir. Cayma bildirimi sonrası bedel iadesi ve geri alma, yürürlükteki yasal sürelere göre gerçekleştirilir.

7. Cayma hakkı istisnaları ve ayıplı ürünler
Tüketicinin talebi veya kişisel ihtiyaçları doğrultusunda üretilen mallarda kanuni cayma istisnaları söz konusu olabilir. Bu husus her ürün için otomatik uygulanmaz ve ürünle ilgili satış öncesinde açıkça bildirilmelidir. Ayıplı maldan kaynaklanan seçimlik haklar saklıdır.

8. Şikâyet, uyuşmazlık ve başvuru
Sipariş, iade ve şikâyetler için bilgi@polariskursun.com veya 0546 262 90 02 üzerinden iletişime geçilebilir. Tüketici hakem heyetleri ve tüketici mahkemelerine başvuru hakkı kanuni sınır ve usuller çerçevesinde saklıdır.

9. Sözleşmenin saklanması
Sipariş onayı sırasında ilgili Ön Bilgilendirme Formu ve sözleşmenin onaylanan sürümü, satıcı ve sipariş bilgileriyle birlikte sistemde kayıt altına alınabilir. Bu genel sayfa, ödeme ekranındaki alıcıya özel ürün, teslimat ve fiyat bilgilerinin yerine geçmez. Çelişki durumunda uygulanabilir emredici tüketici hukuku hükümleri geçerlidir.
TEXT,
            ],
            'on-bilgilendirme-formu' => [
                'title' => 'Ön Bilgilendirme Formu',
                'body' => <<<'TEXT'
POLARİS KURŞUN – MESAFELİ SATIŞ ÖN BİLGİLENDİRMESİ

Satıcı: Zoka Balık Av Malzemeleri Mücahit Meydan
Adres: Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir
Telefon: 0546 262 90 02
E-posta: bilgi@polariskursun.com

Sipariş öncesinde aşağıdaki bilgileri kontrol ediniz:

1. Ürün ve özellikleri: Satın almak istediğiniz ürünün adı, gramajı, varsa iğne kalınlığı gibi seçeneği, adedi ve teknik açıklamaları ilgili ürün sayfası ile sepetinizde bulunur.
2. Ödenecek tutar: Ürün birim fiyatı, miktara göre toplam tutar, varsa indirim ve teslimat ücreti, vergi dâhil toplam ödeme bedeli ödeme sayfasında gösterilir.
3. Teslimat: Teslimat adresi ve yöntemini sipariş sırasında belirtirsiniz. Stok/üretim ve yoğunluk nedeniyle kargoya verme süresi 3–4 iş gününe kadar uzayabilir. Somut siparişiniz için gösterilen teslimat bilgilerini dikkate alınız.
4. Ödeme: Mağazada etkin olan ödeme seçeneği sipariş ekranında belirtilir. Havale/EFT yaparken yalnızca o sipariş için gösterilen banka bilgilerini kullanınız ve açıklamaya sipariş numaranızı yazınız.
5. Cayma hakkı: Tüketici, uygulanabilir istisnalar saklı olmak üzere, malı teslim aldıktan itibaren 14 gün içinde cayma hakkını kullanabilir. Yasal istisnalara giren kişiye özel üretim ürünleri için satış öncesi açık bilgilendirme yapılması gerekir. Ayıplı mala ilişkin kanuni haklar ayrı olarak devam eder.
6. İade kanalı: Cayma ve iade başvuruları bilgi@polariskursun.com adresine veya diğer açık iletişim kanallarına iletilebilir. İade adresi ve süreç Teslimat ve İade Şartları'nda açıklanır.
7. Sipariş düzeltme: Siparişi onaylamadan önce sepet, ürün miktarı, adres ve faturaya ilişkin alanları kontrol ederek gerekli düzeltmeleri yapabilirsiniz.
8. Ödeme yükümlülüğü: Siparişi onaylamanız, seçtiğiniz ödeme yöntemine göre sipariş tutarını ödeme yükümlülüğü doğurur.
9. Başvurular: Uyuşmazlıklarda kanunen yetkili tüketici hakem heyeti/mahkemesi yolları açıktır.

Bu sayfa genel bilgilendirmedir. Alıcıya özgü ürünler, fiyatlar, toplam tutar, teslimat adresi ve sipariş bedeli için ödeme ekranındaki güncel sipariş özeti esas alınır. Mevcut Mesafeli Satış Sözleşmesi ve Teslimat ve İade Şartları birlikte okunmalıdır.
TEXT,
            ],
            'teslimat-ve-iade' => [
                'title' => 'Teslimat ve İade Şartları',
                'body' => <<<'TEXT'
POLARİS KURŞUN – TESLİMAT, İPTAL VE İADE ŞARTLARI

1. Sipariş hazırlama ve teslimat
Siparişler stok, üretim ve sevkiyat durumuna göre hazırlanır. Mevcut mağazadaki bilgilendirmeye göre yoğun talep veya üretim ihtiyacında siparişin kargoya verilmesi 3–4 iş gününü bulabilir. Teslimat süresi, seçilen taşıma yöntemi ve adrese göre değişir. Ürün, mevzuattaki azami süreler saklı kalmak üzere belirtilen adrese gönderilir. Güncel kargo ücreti ve ücretsiz gönderim eşiği ödeme ekranında gösterilir; önceki siteye ait fiyatlar otomatik olarak bu sistemde sabitlenmez.

2. Sipariş iptali
Henüz gönderilmemiş siparişler için sipariş numarasıyla bilgi@polariskursun.com adresine başvurabilirsiniz. Sipariş hazırlanmış veya kargoya verilmişse işlem durumu değerlendirilerek kanuni iptal/cayma haklarınız çerçevesinde yönlendirme yapılır.

3. Cayma hakkı
Tüketici, yasal istisnalar saklı kalmak üzere, teslim tarihinden itibaren 14 gün içinde gerekçe göstermeden sözleşmeden cayabilir. Bu süre içinde bildirim için e-posta, kanunen geçerli diğer yazılı yollar veya mağazanın destek kanalları kullanılabilir.

4. İade adresi ve iletişim
Zoka Balık Av Malzemeleri Mücahit Meydan
Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir
Telefon: 0546 262 90 02
E-posta: bilgi@polariskursun.com

Ürünü göndermeden önce iade talebinizi ve sipariş numaranızı bildiriniz. Gerekli gönderim bilgileri ve uygulanabilir taşıyıcı yönlendirmesi tarafınıza iletilir.

5. İadenin değerlendirilmesi
Ürünün güvenli biçimde, varsa tamamlayıcı parçaları ve faturaya ilişkin bilgilerle birlikte gönderilmesi işlemleri kolaylaştırır. Kanuni cayma hakkını ortadan kaldıracak ek koşullar uygulanmaz. Normal kullanım dışındaki işlemlerden kaynaklanan değer kaybı, yürürlükteki mevzuat çerçevesinde değerlendirilir. Cayma sonrası bedel iadesi ve iade giderleri tüketici mevzuatına uygun yürütülür.

6. Cayma hakkı istisnaları
Kişiye özel talep veya kişisel ihtiyaçlara göre hazırlanan ürünler gibi Mesafeli Sözleşmeler Yönetmeliği kapsamındaki istisnalar söz konusu olabilir. Standart Polaris ürünlerinin tamamı istisna kabul edilmez. İlgili istisna ürün ve sipariş öncesinde açıkça belirtilmelidir. Ayıplı mallardaki kanuni haklar her hâlükârda saklıdır.

7. Destek
İade, yanlış ürün, teslimat hasarı veya eksik ürün için sipariş numaranızla bilgi@polariskursun.com adresine başvurabilirsiniz. Siparişinizi hesabınızdan takip edebilir ve durum güncellemelerini görüntüleyebilirsiniz.
TEXT,
            ],
            'cerez-politikasi' => [
                'title' => 'Çerez Politikası',
                'body' => <<<'TEXT'
POLARİS KURŞUN – ÇEREZ POLİTİKASI

1. Çerezler ve yerel depolama
Polaris Kurşun çevrimiçi mağazası, oturum açma, sepet işlemleri, form güvenliği ve alışverişin sürdürülmesi için gerekli teknik çerezleri kullanabilir. Tarayıcıda çerezlere ek olarak çerez tercihiniz gibi bazı bilgiler yerel depolamada (localStorage) saklanabilir.

2. Zorunlu çerezler
Oturumun korunması, sepetin yönetilmesi ve güvenli form işlemleri için gereken çerezler, hizmetin teknik olarak işlemesi amacıyla kullanılır. Bunlar olmadan bazı temel özellikler çalışmayabilir.

3. Analitik ve pazarlama çerezleri
Mağaza arayüzünde analitik ve pazarlama kategorileri ayrı seçenekler olarak bulunur. Bu kategoriler başlangıçta kapalıdır. Gelecekte ilgili araçlar etkinleştirilirse bunların çalıştırılması uygun aydınlatma ve rızaya bağlı olmalıdır. Çerez tercih ekranındaki onay, site sahibinin ayrıca açıkça tanımlamadığı hiçbir yeni reklam veya analiz hizmetini kendiliğinden etkinleştirmez.

4. Tercihlerin yönetimi
Ziyaretçi çerez bildirimindeki Kabul Et, Reddet veya Tercihler seçeneklerini kullanabilir. Footer'daki Çerez Tercihleri alanından karar daha sonra değiştirilebilir. Tercihler tarayıcıda yaklaşık 180 gün saklanabilir; tarayıcı ayarlarından da silinebilir.

5. Üçüncü taraf hizmetler
Google ile giriş ve etkinleştirilmiş ödeme sağlayıcılarının kendi hizmetleri üzerinden çalışması hâlinde kendi çerez/teknik kayıt ve gizlilik şartları söz konusu olabilir. Bu hizmetlerle ilgili güncel uygulamaları kendi metinleri üzerinden ayrıca inceleyiniz.

6. Kişisel veriler ve iletişim
Kişisel veri işleme süreçlerine ilişkin KVKK Aydınlatma Metni ve Gizlilik Politikası bu metnin tamamlayıcısıdır. İlgili talepler için bilgi@polariskursun.com adresinden iletişim kurulabilir.
TEXT,
            ],
            'islem-rehberi' => [
                'title' => 'İşlem Rehberi',
                'body' => <<<'TEXT'
POLARİS KURŞUN – ALIŞVERİŞ İŞLEM REHBERİ

1. Ürün seçimi
Kategori veya ürün arama alanından ilgilendiğiniz malzemeyi seçebilirsiniz. Surf kurşunlarının farklı gramajları mağazada ayrı ürün olarak listelenebilir. Ürün detayında gramaj, varsa model, iğne kalınlığı gibi diğer seçenekler, adet, fiyat ve stok bilgilerini inceleyiniz.

2. Sepete ekleme
Seçtiğiniz ürün ve miktar sepetinizde gösterilir. Aynı kategorideki başka ürünlerden de ayrı miktarlar belirleyebilirsiniz. Ödeme öncesinde ürün/adet seçimlerini sepet ekranında değiştirebilir veya ürünleri çıkarabilirsiniz.

3. Teslimat ve fatura bilgileri
Ödeme adımında alıcı adı, iletişim, teslimat adresi ve bireysel/kurumsal fatura seçeneği gibi istenen alanları doldurunuz. İl ve ilçe bilgileri ile cep telefonu numarasını kontrol ediniz. Hatalı bilgi girilmişse siparişi onaylamadan önce ilgili alanı düzeltebilirsiniz.

4. Ödeme ve sipariş özeti
Mağazada etkin ödeme yöntemleri ödeme sayfasında gösterilir. Ürün bedeli, varsa indirim, kargo bedeli ve toplam tutarı kontrol ediniz. Havale/EFT siparişlerinde banka hesabı ve sipariş numarası siparişe özel ödeme ekranında gösterilebilir; ödeme bildirimi adımı ayrıca bulunabilir.

5. Ön bilgilendirme ve sözleşme onayı
Siparişinizi tamamlamadan önce Ön Bilgilendirme Formu ve Mesafeli Satış Sözleşmesi bağlantılarını inceleyiniz. Ödeme yükümlülüğü doğuran onay verildiğinde sipariş oluşturulur. Üyelik sözleşmesi ile pazarlama izni birbirinden bağımsızdır; kampanya izni zorunlu değildir.

6. Siparişin takibi
Hesabınızdan sipariş geçmişini ve siparişinizin işlem durumunu takip edebilirsiniz. İlgili hizmet etkinse kargo takip bilgisi de sipariş üzerinde gösterilir. Destek gerektiğinde sipariş numaranızla iletişim kanallarına başvurabilirsiniz.

7. Cayma, iade ve şikâyetler
Teslimat veya iade taleplerinde Teslimat ve İade Şartları'ndaki iletişim kanallarını kullanınız. Ayrıntılı haklar Mesafeli Satış Sözleşmesi'nde yer alır.

8. Sözleşme kayıtları
Yeni sipariş akışında, onaylanan sözleşmelerin sürüm ve içerik kayıtları siparişle ilişkilendirilebilir. Siparişe ilişkin bilgiler hesap ve destek kanalları üzerinden, uygun erişim kontrolleri çerçevesinde talep edilebilir.

Satıcı: Zoka Balık Av Malzemeleri Mücahit Meydan
İletişim: bilgi@polariskursun.com
TEXT,
            ],
        ];
    }
}
