# NEWTIC satış sonrası işlemler

Müşteri hesabındaki sipariş bağlantısından veya imzalı misafir takip bağlantısından talep açılır. Yönetici, İptal ve İade Talepleri menüsünde talebi görür. Bir siparişte tek açık talep vardır. Ödeme, kargo ve stok ayrı izlenir.

PayTR: https://dev.paytr.com/iade-api ve https://dev.paytr.com/durum-sorgu. İade ana tutarı kuruş bazında hesaplanır ve TL biçiminde gönderilir. Vade farkı ayrıca eklenmez. Gönderimden önce sağlayıcıdaki toplam iade tutarı ve işlem modu kontrol edilir. Belirsiz işlem tekrar gönderilmez; referansla durum sorgulanır. Bu sürümde gerçek taksitli iade sağlayıcı üzerinde uçtan uca doğrulanmamıştır.

Kargo: taşıyıcı panelinden alınan iade kodu müşteriye iletilir. Otomatik iade gönderisi ve kargo iptal API'si sağlayıcı şeması doğrulanmadan etkinleştirilmez. Yasal iade süreleri manuel takip edilir. İade talebi tek başına gönderiyi durdurmaz.

Banka: gerçek transferi yönetici bankadan yapar ve referansı kaydeder. Test havale siparişlerinde gerçek para iadesi kaydedilemez. Ödenmemiş havale tam iptali için banka hareketi kontrolü gerekir. PayTR bekleyen ödeme iptali henüz otomatik değildir.

Canlı PayTR iadeleri yeni kurulumda kapalıdır. Yönetici test iadesini doğruladıktan sonra PayTR ayarlarında canlı iade yetkisini açar. Gerçek ödeme modu ve HTTPS mağaza adresi zorunludur. Önceki iade kayıtları ve sağlayıcı bakiyesi karşılaştırılır.

1.0.11: `returns:reconcile` cron yalnızca durum sorgusu yapar; iade POST işlemini tekrarlamaz. Son kontrol zamanı admin ekranında gösterilir. E-posta merkezinde yeni iade olayları seçilebilir; mevcut seçili olay listeleri ve SMTP etkinlik durumu değiştirilmez. Test mağazaları yalnızca güvenli test alıcısına gönderir.
