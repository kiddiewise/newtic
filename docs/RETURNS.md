# NEWTIC satış sonrası işlemler

Müşteri hesabındaki sipariş bağlantısından veya imzalı misafir takip bağlantısından talep açılır. Yönetici, İptal ve İade Talepleri menüsünde talebi görür. Bir siparişte tek açık talep vardır. Ödeme, kargo ve stok ayrı izlenir.

PayTR: https://dev.paytr.com/iade-api ve https://dev.paytr.com/durum-sorgu. İade ana tutarı kuruş bazında hesaplanır ve TL biçiminde gönderilir. Vade farkı ayrıca eklenmez. Gönderimden önce sağlayıcıdaki toplam iade tutarı ve işlem modu kontrol edilir. Belirsiz işlem tekrar gönderilmez; referansla durum sorgulanır. Bu sürümde gerçek taksitli iade sağlayıcı üzerinde uçtan uca doğrulanmamıştır.

Kargo: taşıyıcı panelinden alınan iade kodu müşteriye iletilir. Otomatik iade gönderisi ve kargo iptal API'si sağlayıcı şeması doğrulanmadan etkinleştirilmez. Yasal iade süreleri manuel takip edilir. İade talebi tek başına gönderiyi durdurmaz.

Banka: gerçek transferi yönetici bankadan yapar ve referansı kaydeder. Test havale siparişlerinde gerçek para iadesi kaydedilemez. Ödenmemiş havale tam iptali için banka hareketi kontrolü gerekir. PayTR bekleyen ödeme iptali henüz otomatik değildir.
