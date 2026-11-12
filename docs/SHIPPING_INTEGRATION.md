# NEWTIC — Kargo Entegrasyonu (Kargo Entegratör) | Başlangıç sürümü

## Mimari

Dört tablo: shipping_providers, shipping_carriers, shipping_warehouses ve shipping_shipments.
Bu, Kargonomi gibi alternatif sağlayıcı adaptörlerini ileride eklememize izin verir.
İlk sağlayıcı Kargo Entegratör. İlk sürüm yalnızca Dummy Kargo TEST gönderilerine izin verir.
DHL ve diğer gerçek kargo şirketleri senkronize edilir ve görüntülenir fakat gerçek barkod oluşturulamaz.

## Kurulum

1. https://app.kargoentegrator.com/ üzerinden hesap aç.
2. DHL anlaşmanı Kargo Hesapları bölümünde tanımla. Dummy Kargo da ekle.
3. Depo Adresleri bölümünden en az bir gönderici depo tanımla.
4. Ayarlar → Satış Kanalları / API bölümünden API anahtarını al.
5. NEWTIC → Kargo Entegrasyonu ekranından API anahtarını şifreli kaydet.
6. Bağlantıyı kontrol et ve hesapları yenile ile GET istekleriyle firma/depo listesini al.
7. Dummy Kargo testini etkinleştir; gerçek kargo şirketleri kapalı kalır.
8. Hazırlanıyor durumundaki ödemesi doğrulanmış test siparişinde Dummy Kargo seç,
   depo, ödeme şekli, paket desisi ve opsiyonel ağırlığı kontrol et.
9. Dummy barkodu oluştur; PDF indir, panelden Dummy durumu değiştir,
   imzalı callback veya Durumu yenile işlevleriyle güncellemeyi doğrula.

## Resmî API uç noktaları

- GET https://app.kargoentegrator.com/api/integration/cargos
- GET https://app.kargoentegrator.com/api/settings/warehouses
- POST https://app.kargoentegrator.com/api/shipments
- GET https://app.kargoentegrator.com/api/shipments/{id}
- GET https://app.kargoentegrator.com/api/print-pdf?shipments[0]={id}

Tüm isteklerde sabit sağlayıcı HTTPS hostu ve Bearer API key kullanılır.
Anahtar browser'a, loglara veya URL'ye verilmez; Laravel encrypted cast ile saklanır.

## Durum bildirimi

Gönderi isteğinde notification_url = https://powderblue-dinosaur-538060.hostingersite.com/kargo/bildirim
Gelen JSON: shipment_id, team_id, status, time, hash.
İmza: hash_hmac('sha512', shipment_id . status . time, API_KEY).
Tekrarlanan ve eski bildirimler atlanır.

- non_processed sadece gönderinin kaydedildiği anlamına gelir; kargolandı sayılmaz.
- shipped / on_transit / at_delivery_center → shipped.
- in_courier → out_for_delivery.
- delivered → completed.
- canceled / failed / on_return / returned otomatik sipariş iptaline ve stok değişimine yol açmaz.
- Takip numarası callback'te gelmez; GET detay yenilemesiyle alınır.
- Ödeme bekleyen sipariş hiçbir zaman kargo callback'iyle ödeme onayı kazanamaz.

## Güvenlik

- Sipariş başına shipping_shipments.order_id UNIQUE.
- Sipariş gönderilmeden önce creating rezervasyon kaydı oluşturulur.
- POST yanıtı belirsizse uncertain durumunda kalır; otomatik tekrar oluşturulmaz.
- API anahtarı değişince test gönderileri kapanır.
- Admin işlemleri auth/admin/CSRF ile korunur.
- Callback CSRF dışıdır ancak yalnızca staging ve HMAC-SHA512 ile kabul edilir.
- PDF admin koruması altında proxy edilir, %PDF- formatı ve 5 MB sınırı kontrol edilir.
- Gerçek DHL gönderisi, kullanıcı onaylı ayrı canlı aşamaya kadar devre dışıdır.

## Üretim öncesi

Gerçek hesap ile canlı DHL yetkisi ve depo doğrulaması, Dummy Kargo başarılı
uçtan uca test, adres/desi kontrolü, çok paket/iade süreçleri, kuyruk/cron
ve gerçek kargo masrafı için ayrı kabul testleri gerekir.

Resmî belgeler:
https://dev.kargoentegrator.com/
https://dev.kargoentegrator.com/authentication/
https://dev.kargoentegrator.com/api/shipments/olusturma/
https://dev.kargoentegrator.com/api/integrations/kargo-listeleme/
https://dev.kargoentegrator.com/api/settings/depo-listeleme/
https://dev.kargoentegrator.com/api/shipments/tek-gonderi/
https://dev.kargoentegrator.com/api/callback/
https://dev.kargoentegrator.com/api/print-pdf/
