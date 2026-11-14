# NEWTIC — 2026 Checkout UX / QA

Tarih: 2026-11-10
Ortam: Hostinger geçici test sitesi `powderblue-dinosaur-538060.hostingersite.com`.
Hedef dal: `feat/phase-01-foundation-homepage`
Canlı `polariskursun.com` bu çalışmanın dışında tutulur.

## Uygulanan tasarım ve güvenlik kararları

- Misafir alışverişi belirgin; üyelik isteğe bağlıdır, giriş isteğe bağlı bir bağlantıdır.
- Sipariş adımları metinle anlatılır; yanıltıcı güvenlik/ödeme simgesi kullanılmaz.
- Mobil formun üstünde kapatılıp açılabilen gerçek sepet özeti ve toplam bulunur; masaüstü özeti kenarda kalır.
- Cep telefonu alanı `05XXXXXXXXX`, boşluklu, `5XXXXXXXXX`, `+905XXXXXXXXX` ve `905XXXXXXXXX` girdilerini normalize eder. Sahte numaralar doğrulamada reddedilir.
- Hatalı alanlar `aria-invalid`, `aria-describedby` ve Türkçe alan mesajlarıyla tanımlanır.
- Aynı gönder düğmesine tekrarlayan tıklamalar engellenir; sunucuda mevcut checkout token ve stok doğrulaması korunur.
- Kargo ve ürün fiyatları sunucuda hesaplanır; müşteri girişinden toplam tutar alınmaz.
- Ödeme kurulumu tamamlanmadığından `NEWTIC_CHECKOUT_TEST_MODE=true` varsayılandır. Gerçek ödeme ve sipariş e-postası gönderildiği iddia edilmez.
- `lang/tr/validation.php` yerelleştirmesi ve örnek `.env` içinde `APP_LOCALE=tr` kullanılır.

## Otomatik testler

`tests/Feature/CheckoutExperience2026Test.php`: misafir checkout görünümü, mobil özet,
telefon normalizasyonu (sipariş ve kayıtlı adres), alan bazlı erişilebilir hata
mesajları ve Türkçe doğrulama metinleri.

Mevcut `CheckoutOrdersTest`, `ShoppingCartTest`, `CartMobileRedesignTest` ve
`WishlistMembershipTest` de regresyon için çalıştırılmalıdır.

## Staging kabul kontrolleri (gerçek tarayıcıda)

- [ ] 390px genişlikte misafir checkout, kargo dahil toplam ve açılır sipariş özeti görünür
- [ ] 768px ve 1440px genişlikte 2 sütunlu checkout taşma olmadan çalışır
- [ ] Üye girişi zorunlu olmadan deneme siparişi kaydedilebilir
- [ ] Giriş yapan üye kayıtlı adres seçebilir, yeni adresle değiştirebilir
- [ ] Bireysel/kurumsal fatura alanları doğru görünür ve doğrulanır
- [ ] Geçersiz e-posta/telefon için Türkçe, alan altı hata mesajı görünür
- [ ] +90 ile başlayan telefon numarası normalize edilir
- [ ] 2 kez hızlı dokunma 2 farklı sipariş üretmez
- [ ] Hatalı stok ve fiyat durumu sipariş oluşturmaz
- [ ] Sipariş başarı ekranı açıkça test siparişi ve ödeme alınmadığını söyler
- [ ] Lighthouse/WCAG kontrolünde form etiketleri, odak sırası ve mobil dokunma hedefleri gözden geçirilir

## Dağıtım

`.github/workflows/checks.yml` geliştirme dalına push sonrası Composer + Vite
build, migrasyon ve PHPUnit testlerini çalıştırır. Staging'e aktarım ancak
`NEWTIC_DEPLOY_ENABLED=true` ve doğrulanmış SSH secret'ları varsa yapılır.
GitHub'a push, tek başına Hostinger'a deploy edildiği anlamına gelmez.

**Önemli:** Hostinger erişimi ve CI sonuçları teyit edilmeden “test ortamında
yayınlandı” veya “bütün testler geçti” denmeyecek.
