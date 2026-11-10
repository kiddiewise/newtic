# NEWTIC — Masaüstü satın alma akışı kabul kontrolleri

Tarih: 2026-11-10
Kapsam: Hostinger staging `powderblue-dinosaur-538060.hostingersite.com`.
İlgili değişiklik: #3 / `feat/desktop-purchase-ux-20261110`.
Hedef dal: `feat/phase-01-foundation-homepage`.

## Beklenen davranışlar
- [ ] Masaüstünde (>=761 px) anasayfa, kategori ve arama sonuçlarındaki ürün kartlarında fiyat altında tam genişlikte **Sepete Ekle** butonu görünür.
- [ ] Stokta olmayan kartlarda **Stokta Yok** görünür ve buton devre dışıdır.
- [ ] Ürün detay sayfasında fiyatın altında adet seçici ve **Sepete Ekle** ilk ekranda görünür.
- [ ] Ürün detayında aynı kategorinin bağımsız SKU'ları ve gramajları ayrı adetlerle tek istekte sepete eklenebilir.
- [ ] Masaüstü üst menüsünde **Sepetim** etiketi görünür.
- [ ] Başarılı eklemeden sonra sağ tarafta hızlı sepet açılır ve toplam/adet güncellenir.
- [ ] 760 px ve altında mevcut mobil akış korunur; masaüstüne özgü etiketler tekrar etmez.
- [ ] Stok sınırı, CSRF ve sepet doğrulaması arka uçta korunur.
- [ ] Staging'de anasayfa, ürün detayı, sepet ve yönetici giriş ekranı hata vermeden yüklenir.

## Dağıtım ve doğrulama
`Tests and assets` GitHub Actions, staging dalına yapılan push sonrası PHP/JS bağımlılıklarını kurar, Vite derlemesini ve Laravel testlerini çalıştırır.
Hostinger'a dağıtım yalnızca `NEWTIC_DEPLOY_ENABLED=true` ve gerekli SSH sırları tanımlıysa çalışır.
Başarılı GitHub push, tek başına dağıtımın tamamlandığı anlamına gelmez.
Canlı `polariskursun.com` kapsam dışıdır.
