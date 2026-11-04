# Polaris Kurşun — Ürün ve deneyim gereksinimleri

## 1. Mağaza konumlandırması
Balıkçılık ekipmanları satan Polaris Kurşun'un yeni mağazası. Kalite beklentisi: Petlebi düzeyinde kolay alışveriş ve mobil hızlı gezinme; tamamen özgün Polaris görsel dili. Türkçe, TRY, responsive, SEO uyumlu ve erişilebilir.

## 2. Mobil ana sayfa
Sıralanabilir vitrin modülleri:
1. Görselden ibaret otomatik ana slider (her slide için ayrı mobil/masaüstü medya, sıra, yayın durumu, opsiyonel tıklama URL'si; *banner üzerinde ek metin, CTA veya kaplama yok*).
2. Yönetici seçimiyle listelenen kategoriler (sıralama ve göster/gizle).
3. `Popüler Ürünler` — otomatik satış sıralaması değil, yöneticinin elle seçtiği ürünler; sırayla ve istenen sayıda.
4. Tekli/ikili/tam genişlik kampanya banner görselleri; ana slider gibi metin bindirilmez.
5. Son eklenenler ve kategori koleksiyonları gibi isteğe bağlı bölümler.
6. Güven ve yardım unsurları, footer, mobil gezinme, arama.

Her blok için aktif/pasif, sıra, görüntüleme ayarları; tasarım bileşenlerini bozmadan yöneticinin güncellemesi.

## 3. Slider özel kuralları
- Veritabanı/kontrol panelinde sabit maksimum slide sayısı dayatma.
- Mobil ve masaüstü banner görsellerinin farklı seçilmesi zorunlu değil ama desteklenmeli; eksikse tanımlı fallback.
- Otomatik slayt geçişi ayarlanabilir; kullanıcı etkileşiminde geçiş kontrolü; dokunmatik kaydırma; görünür/erişilebilir kontrol; prefers-reduced-motion.
- İlk görsel yüksek öncelikli, diğerleri düşük öncelikli yüklenerek LCP korunmalı; CSS aspect ratio ile CLS engellenmeli.
- Upload için güvenli MIME, boyut, çözünürlük ve kaydetme stratejisi; orijinal eserleri telif hakkına uygun kullan.

## 4. Katalog ve benzersiz detay sipariş modeli
- Ayrı gramajlar ve diğer kategorideki ürünler bağımsız Product kayıtlarıdır. Asla sırf gramaj için tek Product/varyant tabanına zorlanmasın.
- Her Product: ID, SKU, slug/URL, ad, açıklama, görseller, stok, fiyat, aktiflik, ana kategori ve diğer kategoriler.
- Ürün detayında açılan ürünün ana kategorisindeki **bütün aktif** ürünler tam olarak erişilebilir olmalı. Çok uzun listelerde aşamalı render/virtualization yapılabilir ama içerik kesilmemeli.
- Görüntülenen üründe vurgulama + miktar 1; diğerlerinde miktar 0. Kullanıcı her satırda ayrı miktar değiştirebilir.
- Sayfada seçilen kalem ve tutarın anlık özeti, mobilde sabit veya kolay erişilebilir tek `Seçilenleri Sepete Ekle` aksiyonu.
- Sunucuda transaction, stok kontrolü, adet validasyonu, güncel fiyat ve yetki denetimi; istemci tarafı hesap güvenilir kaynak değildir.
- Aynı kategorideki ürünlerle alışveriş bir kullanıcı davranışı kuralıdır, klasik cross-sell değil. Sırf `benzer ürünler` önerisine indirgeme.

## 5. Gelecek fazlar
- Sepet, misafir satın alma, üye hesapları, ödeme/kargo, sipariş ve stok yönetimi, kuponlar, promosyon kuralları, bildirimler.
- WooCommerce ürün, kategori, görsel, stok, müşteri ve sipariş verisi aktarımı; kişisel veriyi güvenli yönetme; SEO URL/301 haritaları.
- Canlıya geçiş öncesi sunucu yapılandırması, kaynak yedekleri, ödeme testleri, veritabanı kontrollü senkronizasyon ve geri dönüş planı.

## 6. Kontrol edilecek başlıca kabul kriterleri
- Ana sayfaya admin panelinden yeni bir slide eklenir ve metin bindirmeden yayınlanır.
- Admin slider sırasını değiştirdiğinde vitrin yeni sırayı gösterir.
- Admin istediği kategorileri ve popüler ürünleri seçip sıralar; vitrin yeni seçimi gösterir.
- 390px mobilde ürünler/menü/slider taşma yapmaz; ürün fotoğrafları tutarlı ölçüdedir.
- Ayrı 75/100/125g ürün URL'leri korunur; 125g açılınca 125g seçili, diğerleri 0'dır.
- Aynı kategorinin birbiriyle ilgisiz ürünleri de ürün detay listesinde görünür; sadece gramajlardan ibaret değildir.
- Birden çok ürün/gramaj ayrı adetlerle tek sepete ekleme işleminde doğru satırlarla kaydedilir.
- Canlı WordPress mağazasında değişiklik yapılmaz; geçici URL arama motorlarına kapalıdır.
