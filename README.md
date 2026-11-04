# newtic — Polaris Kurşun 2.0

Polaris Kurşun için mobil öncelikli, yüksek performanslı, özgün Laravel e-ticaret platformu.

- **Geliştirme adresi:** https://powderblue-dinosaur-538060.hostingersite.com/
- **Mevcut canlı mağaza:** https://polariskursun.com/ (WordPress/WooCommerce; geliştirme süresince dokunulmaz)
- **Referans deneyim:** Petlebi'nin mobildeki gezinme kolaylığı. Marka tasarımları, görseller, metinler ve varlıkları kopyalanmaz.
- **Dil / para birimi:** Türkçe / TRY

## Değiştirilemez ürün kuralları

1. Her gramaj/ürün kendi veritabanı kaydına, fiyatına, stokuna, SKU'suna ve detay URL'sine sahiptir. Bunlar tek varyasyonlu ürüne **birleştirilmez**.
2. Ürün detayına girildiğinde, görüntülenen ürünün bağlı olduğu **ana kategorideki bütün aktif ürünler** aynı sayfada listelenir; yalnız gramaj alternatifleri değil.
3. Açılan ürün, başlangıçta seçili/vurgulu görünür ve miktarı varsayılan olarak 1'dir. Diğer ürünlerin miktarları başlangıçta 0'dır.
4. Her satırda bağımsız miktar kontrolü vardır. Kullanıcı farklı ürünleri birlikte seçerek tek işlemde sepete ekler.
5. Sunucu fiyatı, stok durumunu, adet limitlerini ve yetkileri yeniden doğrular; tarayıcıdaki fiyatlar otorite değildir.

## Ana sayfa içerik yönetimi

- **Ana slider:** Sadece görseller. Overlay başlık, açıklama ve düğme **yoktur**. Ayrı mobil ve masaüstü görseli, isteğe bağlı bütün banner bağlantısı, sıralama, yayın durumu ve otomatik kaydırma.
- **Kategoriler:** Yönetici hangi kategorilerin görüneceğini ve sırasını seçer.
- **Popüler ürünler:** Yönetici hangi bağımsız ürünlerin görüneceğini ve sırasını seçer.
- **Diğer bloklar:** Kampanya görselleri, yeni ürünler ve kategori vitrinleri; görünürlük ve sıralama yönetilir.
- Blok türleri yönetim panelinden eklenir, açılıp kapatılır ve yeniden sıralanır.

## Tasarım ve teknoloji

- Mobile-first, özgün premium tasarım; okunabilir Türkçe tipografi ve tutarlı SVG ikonlar.
- Laravel + Blade + Livewire + Alpine + Tailwind CSS (kurulum anında PHP ve paket uyumluluğu doğrulanır).
- MySQL (Hostinger), geliştirme/test için izole veritabanı; görsel optimizasyonu ve önbellekleme.
- İlk aşamada gerçek ödeme alınmaz ve gerçek müşteri verisi kullanılmaz.

## İş akışı

1. [Codex talimatları](AGENTS.md)
2. [Ürün gereksinimleri](docs/PRODUCT_REQUIREMENTS.md)
3. [İlk geliştirme görevi](docs/CODEX_PHASE_01.md)

Codex, ilgili dokümanları okuyup görev için ayrı bir `feat/` dalı oluşturarak uygulama geliştirir; test sonuçları ve kurulum yönergeleriyle pull request açar.

**Güvenlik:** `.env`, şifreler, tokenlar, ödeme anahtarları ve müşteri verileri asla Git'e eklenmez.
