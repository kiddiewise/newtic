# NEWTIC Yönetim Panelinden Güncelleme

Müşteri güncelleyicisi yeni sürüm kontrolü, bildirim ve
yönetici onayıyla tek seferlik yükseltme sağlar.

## Resmî sürüm yayını

1. NEWTIC main üzerinde geliştir, tüm testleri bitir.
2. VERSION dosyasına 1.0.0, 1.2.0 gibi üç parçalı sürüm yaz.
3. Aynı commit üzerine eşleşen v1.0.0, v1.2.0 Git etiketi oluştur.
4. Publish NEWTIC customer release GitHub Actions iş akışı, PHP 8.3
   bağımlılıkları ve derlenmiş JS/CSS içeren newtic-customer-install.zip
   arşivini GitHub Release'e bağlar.
5. GitHub Release API bu varlığa ait SHA256 özetini yayımladığında
   mağazalar güvenilir paketi algılar.

main dalındaki her commit otomatik güncelleme değildir. Yalnızca
kiddiewise/newtic deposunda yayımlanmış, önizleme olmayan, SHA256
özeti bulunan resmî sürümler müşteriye önerilir.

VERSION=0.0.0 bir başlangıç yer tutucusudur; gerçek bir v0.0.0 sürümü
yayımlanmış sayılmaz. İlk resmî sürüm etiketlenmeden önce bu değeri
gerçek sürüm numarasına yükseltin. Örnek 1.2.0 mevcut bir release değildir.

## Mağazada görünüm

- Yönetici ana sayfasında yeni sürüm bildirimi.
- Sol menüde Yazılım Güncellemeleri bağlantısı ve bildirim sayacı.
- Mevcut sürüm, son sürüm, sürüm notları, güncelleme durumu.
- Otomatik kontrol: 6 saatte bir, müşterinin mevcut cron kaydıyla.
- Elle Güncellemeleri kontrol et, ardından Güncellemeyi yükle.
- Yönetici yedeği onaylamadan yükleme başlamaz.
- Güncelleme sırasında bakım modu ve işlem sonucunun kaydı.

## Güvenlik ve kurulum

- Önceden paketlenmiş ZIP, üretim vendor ve Vite derlemesini içerir:
  müşteri hostinginde Node.js veya Composer çalıştırılmaz.
- İndirme URL'si sabit resmî depoya sınırlandırılır.
- ZIP'in dış SHA256 özeti, kaynak/frontend revision ve tüm paket
  dosyalarının SHA256 kayıtları kontrol edilir.
- Arşivde dizin geçişi, symlink, aşırı büyük içerik engellenir.
- .env, kullanıcı yüklemeleri, özel storage, veritabanı dosyaları ve .git
  değiştirilmez.
- Mevcut müşteri veritabanında yalnızca php artisan migrate --force
  çalışır; db:seed ve sıfırdan kurulum çalıştırılmaz.
- Kaynak dosyalar bakım modunda güncellenir. Doğrulama/kurulum hatasında
  değişen kod dosyalarının ön kopyaları geri alınmaya çalışılır.
- MySQL DDL geri alma garantisi yoktur. Yönetici bu nedenle önceden
  geçerli Hostinger dosya/veritabanı yedeğini onaylar.
- Güncelleme aşamasında geçici disk alanı gerekir; bittiğinde
  geçici ZIP ve geri dönüş dosyaları silinir.

## Sınırlar ve kabul testleri

- PHP 8.3+, ZipArchive, MySQL, outbound HTTPS, CLI cron ve dosya
  güncelleme izinleri gerekli.
- Webroot'u public_html ayrı olan mağazada yazılım klasörü ile
  public_html/build ve public_html/assets eşit sürüme taşınır.
- public_html/storage asla temizlenmez.
- Canlıya geçişten önce en az iki ayrı Hostinger kurulum kopyasında
  vX.Y.Z -> vX.Y.(Z+1) geçişi, sipariş/veritabanı/görsel koruma,
  backend/frontend uyumu ve başarısız güncelleme testi yapılmalı.
- Sağlayıcının PHP upload limitleri bu özellik için geçerli değildir:
  ZIP doğrudan GitHub'dan CLI tarafından indirilir.
- İlk dağıtım hâlen mevcut güvenli kurulum/yayın sürecinden yapılmalıdır.
  Bir kez güncelleyici kurulduktan sonra sonraki resmî sürümler panelden
  yüklenebilir.
