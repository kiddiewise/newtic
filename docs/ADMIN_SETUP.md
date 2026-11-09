# NEWTIC Yönetim Paneli — Kurulum ve kullanım

Geliştirme dalındaki bu özellik Hostinger'a **henüz dağıtılmadı**.

## Güvenlik

- Giriş adresi: `/admin/login`
- Yönetim ana sayfası: `/admin` (oturum + `is_admin` kontrolü zorunlu)
- `/admin/login` için dakikada 5 giriş denemesi limiti
- Tüm değişiklik yapan isteklerde CSRF; bannerların ait oldukları bölüm doğrulanır
- Banner/kategori görsellerinde yalnız JPEG, PNG, WebP, AVIF; boyut ve MIME kontrolü; SVG/HTML yüklemelerine izin verilmez
- `.env` veya yönetici parolası kaynak kodunda bulunmaz
- Public disk ve Laravel `storage:link` doğru yapılandırılmadan yüklenen medya web'den erişilemez

## İlk yönetici oluşturma

Uygulama kurulup DB migration'ları çalıştıktan sonra Hostinger SSH terminalinde uygulama kökünde:

```bash
php artisan newtic:admin
```

Komut e-posta, ad ve **gizli girilen** en az 12 karakterli parola ister. Kullanıcı varsa rol yükseltme ve yeni parola için ayrıca onay ister. **Bu bilgileri sohbet, GitHub veya terminal geçmişine yazma.**

## Panelde neler yapılır?

1. `/admin` → Ana sayfa bölümlerini listeler; yeni slider/kategori/popüler ürün/banner modülü ekler.
2. Her bölümün `Düzenle` sayfasında bölüm sırası, başlığı, aktifliği kaydedilir.
3. Slider/banner bölümlerinde ayrı mobil ve masaüstü dosyaları, isteğe bağlı https/http banner bağlantısı, aktiflik, sıra düzenlenebilir. Başlık/açıklama overlay alanı yoktur.
4. Kategoriler ve popüler ürünler bölümünde istenen kayıtlar seçilir; sayı ile sıralama belirlenir.
5. Kategoriler bölümünde her kategorinin kısayol görseli ayrı yüklenebilir.

## Eksikler

- Gerçek ürün/kategori oluşturma/düzenleme ekranları henüz eklenmedi.
- Sürükle-bırak yerine şimdilik sıralama numarasıyla değişiklik yapılır.
- Eski medya dosyalarının otomatik temizliği/medya kütüphanesi sonraki fazda geliştirilecek.
- Gerçek sepet/checkout, ödeme/kargo ve WooCommerce aktarımı henüz yoktur.
- Hostinger'a dağıtım yapılmadan gerçek tarayıcı görünümü doğrulanamaz.
