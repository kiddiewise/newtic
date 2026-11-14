# NEWTIC — Hostinger staging kurulumu

Geliştirme adresi: https://powderblue-dinosaur-538060.hostingersite.com/

**UYARI:** Bu doküman dağıtımın yapıldığı anlamına gelmez. Canlı `polariskursun.com` WordPress mağazasına dokunma.

## Gereksinimler

- PHP 8.3+ ve Laravel 13 ile uyumlu gerekli PHP uzantıları: openssl, mbstring, pdo_mysql, fileinfo, tokenizer, xml, ctype, json
- Composer 2, Node.js 20+ ve npm (sunucuda Node bulunmuyorsa build dosyaları güvenli CI işleminde hazırlanabilir)
- Yeni **ayrı** MySQL veritabanı ve kullanıcı; gerçek parolaları GitHub veya sohbet mesajında paylaşma
- SSL ve web kökü olarak Laravel projesinin sadece `public/` dizini

## Güvenli kurulum yaklaşımı

1. GitHub `main` içinden **onaylanmış ve test edilmiş** commit'i deploy et. PR dalını otomatik production'a dağıtma.
2. Projeyi web kökü dışında, ör. `~/domains/powderblue-dinosaur-538060.hostingersite.com/newtic` konumuna yerleştir.
3. `public/` dizini Hostinger web root'u olmalı; üst dizinlerde bulunan `.env`, `vendor`, `storage` dosyaları doğrudan HTTP ile erişilememeli. Hostinger planındaki document-root imkanları doğrulanmadan symlink veya public_html'e tüm proje kopyalama yapma.
4. `composer install --no-dev --optimize-autoloader --no-interaction` çalıştır.
5. `.env.example` dosyasını sunucuda `.env` olarak kopyala, sonra sunucuya özel gizli değerleri yalnız orada ayarla. `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://powderblue-dinosaur-538060.hostingersite.com`, `DB_CONNECTION=mysql` ve doğru `DB_*` değerleri.
6. `php artisan key:generate`, ardından yeni DB üzerinde `php artisan migrate --force`.
7. `npm install && npm run build` ile Vite dosyalarını oluştur (veya onaylı pipeline ile oluşturulan `public/build` çıktılarını aktar).
8. Gerekliyse `php artisan storage:link` ve `php artisan optimize`. `storage/` ve `bootstrap/cache/` yazılabilir olmalı.
9. Yerel demo vitrin için isteğe bağlı: `php artisan db:seed --class=PolarisDemoSeeder --force`. Demo ürün ve fiyatlar gerçek satış verisi değildir. Staging dışında çalıştırma.
10. Geçici domain için `robots.txt` ve HTML meta `noindex` aktif; HTTP Basic Auth ile ayrıca koru. Gerçek e-posta/ödeme servislerini devre dışı bırak.
11. SSL, ana sayfa, kategori, ürün, 404 ve PHP loglarını kontrol et. Her deployda migration durumunu ve arşivlenmiş yedeği kontrol et.

## Notlar

- Admin CMS ve gerçek sepet/checkout bu ilk PR kapsamı dışında; bunlar ayrı PR ile teslim edilecektir.
- .env, anahtarlar, ödeme tokenları, gerçek müşteri veya sipariş verileri GitHub'a yüklenmez.
- `migrate:fresh` staging/prod üzerinde hiçbir zaman çalıştırılmamalıdır.
