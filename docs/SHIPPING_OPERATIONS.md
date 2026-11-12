# NEWTIC — Kargo durum operasyonları (staging-only)

## Durum doğruluğu

Kargo Entegratör'ün resmî API enum listesi 11 gönderi durumunu tanımlar:
draft, non_processed, shipped, on_transit, at_delivery_center, in_courier,
delivered, on_return, returned, canceled, failed.

NEWTIC bunları `ShippingStatus` üzerinden Türkçe etiket ve açıklamayla gösterir;
`shipping_shipments.remote_status` ayrıntılı durumun asıl kaynağıdır.
`orders.status` sadece müşteriye dönük kaba sipariş aşamasıdır.
Siparişin parasal ödeme durumu / tahsilat ve stok miktarı kargo API'si
tarafından değiştirilmez.

## Neden 15 dakikada bir GET gerekiyor?

Resmî callback dokümanında `draft`, `canceled`, `failed` için gönderi bazlı
callback üretilmediği belirtilmektedir. Bu nedenle sadece webhook yeterli
değildir. Kod, `GET /api/shipments/{id}` ile eski veya kaçırılmış
durumları yeniden kontrol eder.

- `php artisan shipping:sync --limit=20`
- Yalnızca NEWTIC staging alan adında ve Dummy Kargo test modunda.
- Sadece daha önce oluşturulmuş, ID'si bilinen Dummy Kargo gönderilerini sorgular.
- Her çalıştırmada en çok 20 API GET; üst limit 50.
- Son API denemesinden itibaren en az 10 dakika geçen kayıtlar seçilir.
- İstek başarısızsa ham sağlayıcı hata metni saklanmaz; sayaç tutulur.
- Mükerrer çağrılar kilit ile engellenir. POST, DELETE, PATCH gönderi işlemi yapılmaz.
- `routes/console.php` Laravel scheduler 15 dakikada bir çalıştıracak şekilde ayarlıdır.
- Hostinger staging dağıtım betiği diğer cron kayıtlarını koruyarak
  `*/15 * * * * cd .../newtic && php artisan shipping:sync --limit=20`
  kaydını bir kez eklemeye çalışır. Başaramazsa dağıtım çıktısında uyarı verir.
  hPanel'den aynı işi elle kurmak gerekebilir. İki cron eklemeyin.
- Canlı DHL gönderilerini etkinleştirecek bir API yazma yolu açılmaz.

## Yönetici iş akışı

`/admin/kargo-operasyonlari`:
- İnceleme bekleyen (ön tanımlı)
- Tüm gönderiler
- Devam eden
- Teslim edilen
- İncelenen
- API bağlantı sorunu

Sağlayıcıda iade, iptal veya hatalı işlem görüldüğünde ilgili gönderiye
gerekçeli inceleme notu yazılabilir. `shipping_exception_reviews` tablosu
işlemi kullanıcı, durum, gerekçe ve tarih ile saklar. Bu buton sadece
"görüldü / operasyon incelemesi yapıldı" anlamına gelir; iade/iptal
komutu veya para iadesi yapmaz.

İade, iptal ya da sorun durumu değiştiğinde yeni kargo durumu yöneticiye
yeniden inceleme olarak sunulur. Geçmiş kayıtlar kaybolmaz.

## Çıkış ve devreye alma kriterleri

1. Dummy Kargo üzerinde tüm 11 durumun Türkçe müşteri ekranları ve
   kargo hareket geçmişi kabul edilir.
2. Resmî callback göndermeyen canceled/failed/draft, cron GET sonucu
   yönetici operasyon merkezine düşer.
3. Kargo durumları hiçbir koşulda ödeme doğrulaması, PAYTR iadesi veya stok
   hareketi yaratmaz.
4. Henüz gerçek kargo firmasına etiket açma, gerçek iade gönderisi,
   kargo gönderisini silme ve PAYTR geri ödeme/chargeback otomasyonu yoktur.
   Bunlar farklı bir operasyon fazında finansal kayıtlar ile ele alınmalıdır.

Resmî kaynaklar:
https://dev.kargoentegrator.com/api/enum-sabitler/
https://dev.kargoentegrator.com/api/callback/
https://dev.kargoentegrator.com/api/shipments/tek-gonderi/
