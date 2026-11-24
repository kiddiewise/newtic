# Bekleyen kart ödemeleri
- Yeni siparişlerde ödeme ekranı açma penceresi 30 dakika. Token talebi ağa çıkmadan önce ödeme girişiminde kayıt altına alınır.
- Daha önce hiçbir token talebi başlamadığı kesin olan yeni sipariş, süresi dolunca stokları bir kez geri alarak kapatılır. Eski kayıtlar bu kapsama alınmaz.
- Başlatılmış ve eski girişimler beş dakikada bir sınırlı grup halinde PayTR Durum Sorgu API ile sorgulanır. Tutar, para birimi, kart tipi ve test/canlı bağlamı uyuşursa doğrulanan ödeme hazırlanıyor olur.
- 004 başarılı ödeme bulunamadı; kesin başarısızlık değildir. Ağ hatası, iade kaydı veya uyuşmazlık durumunda otomatik stok açılmaz. İmzalı başarısız callback mevcut güvenli stok iadesi akışını çalıştırır.
- Yönetici sipariş detayından sorgu yapabilir. Cron sağlık merkezi üzerinden izlenir. Yeni bir gerçek ödeme, iade veya tahsilat işlemi başlatılmaz.
- Kaynaklar: https://dev.paytr.com/durum-sorgu ve https://dev.paytr.com/iframe-api/iframe-api-2-adim .
