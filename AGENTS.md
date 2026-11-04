# AGENTS.md — newtic proje geliştirme kuralları

Bu dosya repoda çalışan Codex ve diğer yazılım ajanları için bağlayıcı proje talimatıdır.

## Misyon
Polaris Kurşun için son derece hızlı, mobil öncelikli, şık, özgün ve yönetilebilir bir Laravel e-ticaret platformu geliştir. Petlebi, yalnız kullanım deneyimi için referanstır; ayırt edici tasarımları ve varlıklarını kopyalama.

## Sabit kapsam
- Canlı `polariskursun.com` WordPress/WooCommerce sitesine, veritabanına, siparişlerine veya DNS'ine dokunma.
- Geliştirme URL'si `https://powderblue-dinosaur-538060.hostingersite.com/`; bu ortama otomatik dağıtım ancak erişim/izin ve teknik koşullar doğrulandıktan sonra yapılır.
- Yeni sürümü gerçek ödemeye açma; test sistemi arama motorlarında noindex olmalı; debug/profiler halka açık olmamalı.
- Her kurşun gramajı ve her ürün ayrı SKU, stok, fiyat ve URL ile korunur. Ürünleri tek varyasyonlu kayıt altında birleştirme.
- Detay ekranı görüntülenen ürünün ana kategorisindeki **bütün** aktif bağımsız ürünleri listeler; açılan ürün varsayılan 1 adet, diğerleri 0 adet. Bağımsız adetler tek işlemle sepete eklenir.
- Aynı ürün birden fazla kategoriye aitse, detay listesinde ürünün `primary_category_id` alanını kaynak kabul et; yoksa açık, deterministik fallback tasarla.
- Ana sliderda **yalnızca görsel** bulunabilir; görselin üstüne başlık, açıklama, CTA yazısı/butonu veya editör içeriği bindirme. İsteğe bağlı banner tıklama bağlantısı olabilir.
- Slider için masaüstü ve mobil görseli, durum, sıralama, otomatik geçiş, dokunmatik kaydırma, erişilebilir kontroller, reduced-motion desteği ve kaymayı önleyen boyutlar sağla.
- Yönetici ana sayfa bloklarını ekleyebilmeli, etkinleştirebilmeli, sıralayabilmeli; kategori ve popüler ürün seçimleri tamamen elle yönetilebilir olmalı.

## Teknoloji / mimari
- Laravel'in Hostinger'ın sağladığı PHP sürümüyle uyumlu güncel kararlı sürümünü kullan; seçimi ve minimum sürümleri README'de belirt. Paketleri kesin sürüm doğrulamasıyla ekle.
- Blade + Livewire + Alpine + Tailwind CSS; karmaşık React SPA mimarisi kurma. Yönetim panelinde Filament kullanılabilir, fakat sürüm uyumluluğunu ve erişim kontrolünü doğrula.
- MySQL odaklı migration; testlerde SQLite kullanılabilirse buna uygun tut.
- Uygulama kökü repo kökü olsun; web sunucusunda yalnız `public/` dışa açılsın. `vendor/`, `.env`, `storage/` web'den servis edilmesin.
- Kodun sınırlarını ayır: models, authorization/policies, service layer, UI components, content management, cart/domain logic.
- İş mantığını Blade içine gömme. Tüm girdileri doğrula. Dosya yüklemede boyut/MIME ve dosya türünü sunucuda kontrol et; HTML/SVG çalıştırılabilir yüklemeleri engelle.
- Admin oturum açma, yetkilendirme, CSRF, rate limit, güvenli parola saklama ve sipariş/ürün düzeyinde doğrulama uygula.

## UX ve performans
- Önce 360–430px mobil tasarla, sonra tablet/masaüstü; yatay taşma yaratma.
- Türkçe karakterler ve uygun font alt kümeleri doğru çalışmalı. Performansı bozan ağır font/ikon paketlerini yükleme; mümkünse optimize edilmiş Lucide SVG'ler ve self-hosted WOFF2.
- LCP <= 2.5s, INP <= 200ms, CLS <= 0.1 iyi bağlantı koşulları altında hedeflenen saha metrikleridir; ölçmeden başarı iddia etme.
- Görsellerde responsive sizes/srcset, boyut/ratio, AVIF/WebP uygunsa sunucu desteğine göre dönüştürme; ilk hero görselini önceliklendir, sonraki banner görsellerini ertele.
- Kritik etkileşimlerde klavye, odak görünürlüğü, ekran okuyucu isimlendirmeleri ve dokunma hedeflerini düşün.
- Boş veri durumları, loading/error durumları, mobil menüler, site araması ve sepet erişimi tasarım sisteminde unutulmasın.

## Kod kalitesi ve teslim
- Her görevde önce mevcut dosyaları incele; kapsam dışı dosyaları silme/yeniden yazma.
- Ana dal yerine `feat/` dalı üzerinde çalış ve pull request aç.
- Migration, model factory, test ve anlaşılır kurulum notlarını birlikte teslim et.
- Başarısız testleri gizleme; test komutlarını ve sonuçlarını PR açıklamasında özetle.
- Üretilen dosyalar için Laravel/PHP biçimlendirme ve test komutlarını çalıştır; çalıştıramadığın adımları açıkça yaz.
- Erişim verilmeden Hostinger'a deploy yapıldığını veya testleri geçtiğini söyleme.
- `.env`, gizli anahtar, üretim verisi veya erişim bilgisi commit etme.
- WooCommerce aktarımı ileriki fazdır: önce salt-okunur analiz, sonra dry-run, tutarlılık kontrolleri ve URL yönlendirme planı.
- İlk geliştirme görevini `docs/CODEX_PHASE_01.md` dosyasından al.
