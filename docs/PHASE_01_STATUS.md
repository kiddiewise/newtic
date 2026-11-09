# NEWTIC — FAZ 01 güncel durum

## GitHub üzerinde tamamlanan ve otomatik testten geçenler

- Resmi Laravel 13 uygulama altyapısı, Composer, Vite ve MySQL/SQLite migration'lar
- Mobil öncelikli, referans mobil alışveriş düzenini izleyen özgün Polaris vitrin tasarımı
- Overlay metni olmayan, ayrı mobil/masaüstü görsel kullanan otomatik slider; swipe, sayı göstergesi, reduced motion
- Yatay kategori sekmeleri, fotoğraflı kategori listesi, kampanya kartları ve popüler ürün vitrini
- Ana sayfa blokları, slider, kategori ve popüler ürün seçimleri için veri modelleri
- Giriş kısıtlamalı admin paneli: `/admin/login`, `/admin`
- Yönetim panelinden banner yükleme/düzenleme/silme, sıralama, aktiflik, tıklama bağlantısı, mobil/desktop görseli
- Yönetim panelinden vitrin bölümü ekleme/sıralama/açma-kapama, kategori ve popüler ürün seçimi, kategori fotoğrafı yükleme
- Özel katalog: her gramaj bağımsız ürün, kendi URL/SKU/stok/fiyatı; aynı ana kategorideki bütün aktif ürünlere detayda adet seçimi önizlemesi
- Demo veriler, örnek SVG illüstrasyonlar, noindex, güvenli upload doğrulaması
- İlk admin'i güvenli oluşturmak için `php artisan newtic:admin` (parola kaynak koda yazılmaz)
- Birim/özellik testleri ve GitHub Actions CI

**Son doğrulanmış CI:** https://github.com/kiddiewise/newtic/actions/runs/42250257838 — PHP bağımlılıkları, frontend build, migration ve 19 test (70 assertion) başarılı.

## Henüz tamamlanmayanlar

- Gerçek çoklu ürün sepete ekleme, ödeme/kargo/checkout ve sipariş altyapısı
- Gerçek ürün/kategori CRUD paneli ve daha gelişmiş medya yönetimi
- WooCommerce'den veri aktarımı ve SEO eşleme/301 yönlendirmeleri
- Gerçek ürün fotoğrafları, marka logo dosyası ve gerçek bannerlar
- Hostinger test domainine dağıtım ve gerçek mobil cihazla görsel/performance kontrolü

## Geliştirme akışı

- GitHub: https://github.com/kiddiewise/newtic
- Geliştirme dalı: `feat/phase-01-foundation-homepage`
- Taslak PR: https://github.com/kiddiewise/newtic/pull/2
- Hedef staging: https://powderblue-dinosaur-538060.hostingersite.com/
- Sunucu kurulumu: [HOSTINGER_SETUP.md](HOSTINGER_SETUP.md)
- Admin kullanımı: [ADMIN_SETUP.md](ADMIN_SETUP.md)
- Mobil tasarım kuralları: [MOBILE_DESIGN_REFERENCE.md](MOBILE_DESIGN_REFERENCE.md)

**Bu sürüm henüz Hostinger'a dağıtılmadı ve canlı `polariskursun.com` mağazası değiştirilmedi.**
