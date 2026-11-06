# FAZ 01 — GitHub'daki ilk uygulama kodu

## Bu ilk PR'da eklenenler
- Resmi Laravel 13 uygulama iskeleti (Composer ve Vite ile kurulur)
- Türkçe, mobil odaklı Polaris mağaza arayüzü ve responsive tasarım
- **Yalnız görsel** kullanan ana slider; dokunmatik kaydırma, otomatik geçiş, erişilebilir kontrol, reduced-motion
- Veritabanıyla sıralanabilen ana sayfa slider/kategori/popüler ürün/banner blokları
- Bağımsız SKU, fiyat, stok ve slug kullanan ürün modeli
- Ürün detayında aynı ana kategorideki tüm aktif ürünler ve bağımsız miktar önizlemesi
- Açıkça demo işaretli seed ve özgün placeholder görseller
- İlk HTTP testleri ve GitHub Actions CI
- Hostinger staging kurulum yönergesi

## Henüz yapılmayanlar
- Yönetim paneli ve güvenli görsel yükleme sistemi
- Gerçek sepet, ödeme, sipariş, stok rezervasyonu, kargo
- WooCommerce'den aktarım, SEO URL haritalaması
- Gerçek logo, gerçek ürün görselleri ve bannerlar
- Sunucu deploy'u ve gerçek tarayıcı/Core Web Vitals ölçümü

## Kontrol
- Bu kod ortamında Composer bağımlılıkları indirilip PHP testleri koşulamadı; GitHub Actions iş akışında çalışması hedefleniyor.
- URL: https://powderblue-dinosaur-538060.hostingersite.com/ (henüz deploy yok)
- Gerçek ürün/fiyat gibi yorumlanmaması için demo seed `DEMO-` SKU ve `is_demo=true` kullanır.
