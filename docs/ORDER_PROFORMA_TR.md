# NEWTIC — Sipariş Promofaturası / Hazırlama Formu

## Kullanım
Admin → Siparişler → ilgili siparişi aç:
- **Promofatura PDF**: Fiyatlı, A4, proforma / operasyon çıktısı indir.
- **Hazırlama Formu**: Fiyatsız, A4 ürün toplama kontrol listesi indir.
- **Önizle / Yazdır**: Fiyatlı belgeyi tarayıcıda aç; yazdır veya PDF olarak kaydet.

Belge güncel ürün fiyatlarını değil, **sipariş oluşturulduğunda saklanan tutarları** gösterir. Ürün adları, gramaj/varyasyon, SKU, miktar, müşteri notu, iletişim ve mevcut adres de sipariş kaydından okunur.

**Adres:** Veritabanında ayrı bir fatura adresi tutulmadığı için siparişteki mevcut teslimat adresi fatura adresi olarak açıkça etiketlenir. Kurumsal fatura türünde firma unvanı, vergi dairesi ve numarası belirtilir. Ayrı fatura adresi talep edilirse önce checkout alanı ve veritabanı şeması geliştirilmelidir.

## Güvenlik ve belge niteliği
- PDF'ler yalnızca Laravel admin kimlik doğrulaması ve yetkisi ardından indirilir; direkt QR bağlantısı anonim erişim vermez.
- QR, **siparişi yöneticide açan normal HTTPS URL'yi** içerir. Şifre, müşteri tokenı veya oturum kodu içermez. Dış QR API'si kullanılmaz.
- Logo, ilgili mağazanın saklanan kendi `desktop_logo_path` ayarından yerel dosya olarak okunur. WebP/JPEG/PNG/AVIF gerekirse GD ile PNG'ye çevrilir. Harici sunucuya yüklenmez.
- PDF `private, no-store`, `X-Robots-Tag: noindex` başlıklarıyla sunulur. Görünür belge önizlemesi yeni sekmededir.
- Önceden doğrulanmamış ödeme ve iptal edilmiş siparişler **uyarıyla** yazdırılır; ödeme doğrulanmış gibi gösterilmez.
- **Bu belge resmî fatura veya e-Arşiv/e-Fatura yerine geçmez.** Resmî faturalar mevzuata ve mağazanın muhasebe entegrasyonuna göre ayrıca düzenlenmelidir.
- Dompdf ve Endroid QR kitaplıkları `composer.lock` içinde sürümleri sabit olacak şekilde dağıtılır; müşteri hostinginde Composer update gerekmez.

## Kabul kontrolü
1. Test siparişinde her iki PDF'yi indir; PDF görüntüleyicide A4 dikey ve düzgün Türkçe karakterleri kontrol et.
2. A4 kağıda bas; hazırlama kutularının işaretlenebildiğini, notların taşmadığını ve QR'nin sağ altta olduğunu doğrula.
3. QR'yi telefondan okut: giriş yapmamışsa yönetici girişine yönlendirir; giriş yapılınca aynı siparişi açar.
4. Kurumsal sipariş, birden fazla satır ve müşteri notuyla test et.
5. Gerçek ödeme doğrulanmadan **ödendi** ifadesi gösterilmediğini kontrol et.
