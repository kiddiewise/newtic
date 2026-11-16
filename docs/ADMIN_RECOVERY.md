# NEWTIC – Yönetici hesabını güvenli kurtarma

Bu yöntem, mağaza sahibi yönetici e-postasını veya parolasını unuttuğunda
kullanılır. Normal müşteri şifre sıfırlama rotası admin şifresi değiştirmez.
Mevcut kullanıcılar, ürünler, siparişler ve APP_KEY korunur.

## Kullanım

Yetkili kişi kendi mağazasının Laravel klasörüne girip çalıştırır:

    php artisan newtic:admin-recovery --private-file

Komut şifreyi hemen değiştirmez. Laravel Password Broker üzerinden
30 dakika geçerli, tek kullanımlık kurtarma kodu çıkarır. Aynı sunucuda
birden çok yönetici varsa işlem reddedilir; otomatik bir hesap seçilmez.

Kurtarma kodu terminal çıktısına, GitHub Actions loglarına ve web sayfasına
yazılmaz. Yalnızca hosting hesabının özel File Manager klasörüne kaydedilir:

    storage/app/private/newtic-admin-recovery/private-admin-recovery.txt

Dosya 0600 izinleriyle tutulur, public_html ve Git deposu dışındadır.
İçinde mevcut yönetici e-postası, kurtarma ekranı ve kodu bulunur.

## Telefonda hPanel ile devam

1. Hostinger hPanel -> File Manager -> ilgili mağazanın gizli
   newtic/storage/app/private/newtic-admin-recovery/private-admin-recovery.txt
   dosyasını aç.
2. Yönetici e-postasını öğren, kurtarma kodunu kopyala.
3. Mağaza domainindeki HTTPS /admin/kurtarma adresini aç.
4. Kodu ve iki kere güçlü yeni şifreyi gir.
5. Sistemin yönlendirdiği /admin/login adresinde dosyadaki yönetici
   e-postasını ve yeni şifreni kullan.

Başarılı şifre değişimi eski oturumları sonlandırır, tek kullanımlık
kodu ve özel bilgi dosyasını siler. Kod ikinci kez çalışmaz. 30 dakika
geçerse işlemi tekrar SSH komutuyla veya yetkili GitHub workflow'u ile
başlatmak gerekir.

## Sinay Design telefon üzerinden CI

Yalnız Sinay Design için tanımlı Recover Sinay Design admin securely adlı
GitHub Actions workflow'u, mevcut doğrulanmış Hostinger SSH bağlantısı
üzerinden bu komutu çalıştırır ve herhangi bir gizli bilgiyi CI loglarına
yazdırmaz. Son adımda normal kullanıcı tarafından tarayıcıda yeni şifre
belirleneceği için otomatik işlem mağaza parolasını değiştirmez.

Yetkisi olmayan kişilerle kurtarma dosyası, admin e-postası, kurtarma
kodu veya yeni şifre paylaşılmamalıdır.
