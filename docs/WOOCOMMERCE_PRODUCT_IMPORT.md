# NEWTIC — WooCommerce ürün aktarımı

Bu aktarım eski polariskursun.com mağazasında hiçbir kayıt oluşturmaz, değiştirmez veya silmez. Doğrudan WooCommerce veri tabanı bağlantısı ya da API anahtarı gerektirmez.

## WooCommerce'den dosya hazırlama

1. WordPress yönetiminde **Ürünler → Tüm Ürünler → Dışa aktar** menüsünü aç.
2. **Tüm sütunları**, **tüm ürün türlerini**, **tüm kategorileri** seç.
3. **Tüm özel meta alanlarını dışa aktar** seçeneğini aç (SEO eklentisi metalarını saklamak için).
4. CSV'yi oluştur ve **NEWTIC → Yönetim → WooCommerce Aktarım** sayfasına yükle.
5. Önizlemede sayıyı ve örnek SKU'ları kontrol et. Başlatmadan ürünlere dokunulmaz.
6. İstersen yeni ürünlerin yayına alınmasını işaretle. **Varsayılan: pasif.**
7. Aktarımı başlat; açık sayfa satırları küçük partiler halinde taşır. Sayfa kapanırsa aktarım sayfasını tekrar açıp sürdür.
8. Raporda başarısız satırları ve görsel uyarılarını kontrol et. Gerekirse CSV yeniden yüklenebilir; Woo kaynak kimliği üzerinden aynı ürünler güncellenir.

## Eşleme ve koruma

- Simple ürün → bir NEWTIC ürünü.
- Variable ana ürün → ürün kopyası yaratılmaz; sadece varyasyonlar için başlık/kategori/açıklama/görsel bilgisi kaynağıdır.
- Variation → **kendi SKU, gramajı, fiyatı, stoğu, ürün ID'si ve detay URL'si olan ayrı NEWTIC ürünü**.
- SKU yoksa Woo ürün ID'sinden WC-{id} üretilir; ID de yoksa otomatik eşleme yapılmaz ve kayıt hata raporuna düşer.
- Kategori hiyerarşileri oluşturulur. Birden fazla kategori ilişkisi korunur; birincisi ürünün ana kategorisi olur.
- Fiyat, indirim, stok, görünürlük, açıklama, kısa açıklama, ağırlık, ölçüler, galeri görselleri ve varsa Yoast/RankMath SEO alanları taşınır.
- Orijinal WooCommerce satırı ve desteklenmeyen meta: alanları **kaynak denetim verisi** olarak saklanır (görünür davranış otomatik taşınmaz).
- Görseller yalnızca https://polariskursun.com/wp-content/uploads/ veya https://www.polariskursun.com/wp-content/uploads/ kaynağından, yönlendirme takip edilmeden, görüntü biçimi/boyutu kontrol edilerek yerel diske kopyalanır. Bu kaynağın dışında kalan CDN görselleri raporlanır ve manuel değerlendirilebilir. En fazla 20 görsel/ürün.
- WooCommerce'de stok miktarı boşsa satışa yanlış stok sayısı sunulmaması için **0** kaydedilir ve uyarı oluşturulur.
- Elle oluşturulmuş bir NEWTIC ürünü ile SKU çakışırsa içe aktarma onu değiştirmez.
- Tekrar aktarım Woo kaynak ID'sine bağlı eşlemeyle günceller; aynı ürünü çoğaltmaz.
- Kayıtlı siparişler, üyeler, mevcut sepetler ve PAYTR ödeme ayarları **bu işlemde taşınmaz veya değiştirilmez**.

## Bilinen veri farkları

WooCommerce CSV'si ürün verileri için tasarlanmıştır; **ürün yorumları**, kategori görsel dosyaları, özel eklentilerin çalışma mantığı, kuponlar, ürün dışı sayfalar ve geçmiş siparişler bu CSV'den kendiliğinden taşınmaz. Özel meta değerlerinin korunması, ilgili WooCommerce eklentisinin NEWTIC'te otomatik çalışacağı anlamına gelmez. Gelişmiş değişken ürün stok yönetimiyle WooCommerce eklentilerine ait seçenekler ayrıca incelenmelidir. İçe aktarma listesi tamamlandıktan sonra ürün/stoğu WooCommerce ile karşılaştırıp son farkları düzeltmek gerekir.

Eski WordPress URL'lerinden yeni NEWTIC URL'lerine **301 yönlendirmesi**, canlı domain geçişi sırasında ayrıca planlanmalıdır. İçe aktarım eski WordPress yönlendirme kurallarını değiştirmez.

## Kapasite ve işletim

- CSV: azami 25 MB ve tek dosyada 10.000 satır; daha büyük dosyalar bölünerek yüklenir.
- Dosya Laravel local özel diskte tutulur; herkese açık medya dizinine aktarılmaz.
- Her işlem admin yetkisi ve CSRF ile korunur.
- İşleyici 2 kaynak satırını bir istekte işler. Admin sayfası açıkken otomatik tekrar eder; arka planda iş yaptığı iddia edilmez.
- Başarısız aktarımlar SKU ve kaynak ID'siyle raporda görünür.

Kaynak: [WooCommerce resmî CSV importer/exporter](https://woocommerce.com/document/product-csv-importer-exporter/).
