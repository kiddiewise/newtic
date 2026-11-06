# NEWTIC — Mobil vitrin tasarım referansı

## Amaç
Kullanıcının 6 Kasım 2026'da gönderdiği Petlebi mobil ekran görüntüsünün **yerleşim ve kullanıcı deneyimi** özelliklerini Polaris Kurşun'a özgün biçimde uyarlamak.

Bu doküman doğrudan logo, ikon, banner, ürün fotoğrafı, marka rengi veya telifli içeriği kopyalama izni değildir.

## 390px mobil için kesin ekran sırası

1. Beyaz üst satır: solda hamburger menü, merkezde Polaris logo işareti, sağda hesap ve sepet ikonları.
2. Büyük ve tek satırlık, yuvarlatılmış arama alanı. Görünür search butonu yuvarlak zemin içinde.
3. Kapsül biçiminde yatay kaydırılabilir ana kategori sekmeleri. Solda ana sayfa ikonu.
4. **Tam ekran genişliğinde, görsel-only otomatik slider.** Mobilde yaklaşık 16:9 oran, kenar boşluğu ve yuvarlak köşe yok. Sağ altta `1 / N` sayaç. Kullanıcı swipe yapabilir.
5. Başlıksız, yatay kaydırılabilir kategori kareleri: açık gri fotoğraf alanı üstte, kategori adı altta. Sonraki kart ekranda kısmen görünür.
6. Yatay kaydırılabilir, düşük yükseklikli yatay promo/banner kartları.
7. **Popüler Ürünler** başlığı ve `Tümünü gör` bağlantısı. Ürün kartları yatay kaydırılabilir.
8. İsteğe bağlı sonraki vitrin modülleri ve footer.

## Masaüstü

Mevcut masaüstü header/search ve genişletilmiş katalog grid düzeni korunabilir; mobil ekran küçültülmüş masaüstü görünümü olmamalı.

## İşlev, performans ve yönetilebilirlik

- Ana slider yalnız görsel içerir; HTML overlay başlık/metin/CTA yoktur. Promosyon yazıları istenirse yüklenen afiş görselinin parçası olabilir.
- Farklı mobil/desktop görselleri ve oranları, dinamik slide sayısı, otomatik geçiş, reduced motion, swipe ve erişilebilir kontrol şart.
- Her kategori kartı fotoğraflı; admin yönetiminde kategori görseli değiştirilebilmeli. Gerçek fotoğraf yoksa telifsiz, yerel demo placeholder.
- Header/kategori/promo/ürün listeleri mümkün olduğunca veritabanı sorgularını çoğaltmadan render edilmeli.
- Sıralama yönetim panelinde değiştirilebilmeli (hero -> kategoriler -> promosyon -> popüler ürünler yalnız demo varsayılan sıra).
- Yönetim paneli, ürün detayındaki çoklu ürün sipariş modeli ve güvenli sepet kuralları aynen korunacak.
- Dar ekranlarda yatay sayfa taşması olmamalı; yalnız modül kendi içinde yatay scroll edebilir.
- Gerçek hesap/sepet işlemleri tamamlanıncaya kadar bu ikonlar çalışıyormuş izlenimi vermemeli.
- Staging ortamında `noindex` ve demo içerik ayrımı korunmalı.

## Repo uygulaması

`resources/views/layouts/store.blade.php`, `resources/views/store/home.blade.php`, `resources/css/petlebi-mobile.css`, `resources/js/app.js`, `tests/Feature/MobileHomepageLayoutTest.php`

Hostinger dağıtımı henüz ayrı bir iştir; bu belge deploy yapıldığı anlamına gelmez.
