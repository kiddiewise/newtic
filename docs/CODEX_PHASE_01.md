# CODEX GÖREVİ — FAZ 01: Laravel temeli + premium mobil ana sayfa + yönetilebilir vitrin

## Kaynaklar
Önce repo kökündeki `AGENTS.md`, `README.md` ve `docs/PRODUCT_REQUIREMENTS.md` dosyalarını eksiksiz oku. Bunlar görev tanımının parçasıdır.

## Hedef
Boş repoyu çalışan, test edilebilir, Hostinger uyumlu bir Laravel uygulamasına dönüştür. Gerçekten işleyen bir mobil ana sayfa ve yönetim panelinde banner/kategori/popüler ürün düzenleme akışını geliştir. Bu fazda ödeme, gerçek sipariş, WooCommerce migrasyonu ve canlı domaine dokunma yok.

## İş planı
1. Yeni `feat/phase-01-foundation-homepage` dalı aç; varsayılan dalı doğrudan değiştirme.
2. Ortama kurulu PHP/Composer/Node sürümlerini doğrula. Laravel'in güncel ve PHP 8.3+ destekli kararlı sürümünü kur; Laravel proje dosyaları depo kökünde yer alsın. Blade + Livewire + Alpine + Tailwind CSS'i uyumlu sürümlerle kur. Kurulum engeli varsa çalışır alternatifi ve gerekçesini PR'da açıkça bildir.
3. Üst düzey tasarım sistemi oluştur: özgün Polaris kimliği; koyu okyanus (`#132D3B`), turkuaz (`#087F8C`), açık zemin; özelleştirilmiş tipografi, tutarlı Lucide SVG ikonlar, modern whitespace; başlıklar, kartlar, tipografi ölçeği, butonlar, yüklenme/boş durumları.
4. Responsive header, ürün arama giriş alanı, kategori erişimi, sepet ikonu, masaüstü menü, mobil alt gezinme ve footer oluştur. Yeni ürün/listing sayfaları için düzgün URL rotaları/placeholder state'leri sağla.
5. Home page blocks için veritabanı tasarla (type, title for admin only, sort_order, is_active, settings JSON vb.). Gerekli ilişkili kategoriler, ürünler, banner/slide kayıtları ve seçime dayalı pivot/sıralama alanlarını kur. Domain modelleri gelecekteki gerçek katalog gereksinimleriyle uyumlu olsun.
6. Ana slider bileşeni: sadece **görsel**, görsel üzerine overlay yazı/başlık/açıklama/buton KESİNLİKLE koyma. Mobil/desktop ayrı medya, opsiyonel bütün slayt linki, otomatik geçiş, dokunmatik swipe, pause ve reduced-motion desteği. Görsel ölçüleriyle CLS engelle; yalnız ilk görseli yüksek öncelikte yükle.
7. Ana sayfa blokları: adminin sıralayabildiği kategori vitrini, adminin seçtiği sırada popüler ürünler, tekli/ikili banner görselleri. Her blok is_active ve sort_order ile yönetilebilir; yönetici seçimi veritabanından gelsin (hard-coded vitrin olmasın).
8. Yetkilendirilmiş /admin arayüzü kur. Filament sürüm uyumunu doğrula; uygunsa Filament ve yalnız admin hesapları, değilse izole Livewire admin ekranları. Admin banner dosyası yükleyebilsin, mobil/masaüstü medya alanlarını güncelleyebilsin, sıralama/yayın durumunu değiştirebilsin; kategori ve popüler ürün seçimlerini değiştirebilsin. Admin oluşturma sürecini dökümante et; sabit varsayılan parola, public demo admin, test e-posta veya secret commit etme.
9. Model factory/seed ile yerel geliştirici için küçük ve açıkça demo etiketli katalog kur; gerçek Polaris ürün görsellerini, markaları ve Petlebi'nin telifli varlıklarını otomatik kopyalama. Gerçek görseller yüklenene kadar premium görünümlü, özgün lokal placeholder grafikler kullan. Staging'de örnek fiyat/stok varsa demo olduğu görünür olmalı.
10. Testler ekle: homepage doğru blok sıralaması, pasif blok/banner gizleme, banner görünümünde overlay metin olmadığı, kategori ve popüler ürünlerin admin seçiminde kalması, admin dışındaki kullanıcıların yönetici ekranına girememesi. Var olan testleri de çalıştır.
11. Hostinger shared hosting için ayrıntılı kurulum belgesi `docs/HOSTINGER_SETUP.md` hazırla: PHP extensions, Composer, public document root, MySQL, `.env`, APP_KEY, storage link, migrate --force, cache/config, build assets, SSL, noindex, cron, çalışma komutları. DNS/üretim site yönetimi değişikliklerine girme.
12. Kod biçimlendirme, test ve varsa frontend build'i çalıştır; sonuçları raporla. PR aç; neyin tamamlandığını, nasıl test edileceğini, deploy için hangi Hostinger bilgilerine ihtiyaç duyulduğunu yaz.

## Kesin başarı koşulları
- Gerçek Laravel kodu; sadece ekran görüntüsü, mock, tasarım tarifi ya da statik HTML teslimi değil.
- Türkçe, çok şık, mobil öncelikli bir ana sayfa; telefon genişliklerinde yatay scroll yok.
- Admin ekranlarından slider, kategori bloğu, popüler ürün bloğu ve banner modülleri yönetilebiliyor.
- Slider yalnız görsel içeriyor.
- Kullanıcı ürüne tıklayabiliyor; kapsam dışı checkout ve kategori içi çoklu ürün sepeti bu fazda tamamlanmadıysa sonraki faz için açıkça bırakılıyor.
- `.env` ve gerçek secret'lar commit edilmiyor.
- Canlı `polariskursun.com` etkilenmiyor; Hostinger'a deploy edildiği doğrulanmadıkça deploy iddiasında bulunulmuyor.
- PR'da test komutları, sonuçları ve eksikler dürüstçe raporlanıyor.

## Çıktı
Yüksek kalite standartlarında çalışan uygulama + migrasyonlar + testler + kullanım/kurulum belgeleri + GitHub PR. Belirsiz küçük arayüz kararlarını en iyi uygulama ilkeleriyle ver; halihazırda kararlaştırılmış hususlarda kullanıcıya yeniden soru sorma.
