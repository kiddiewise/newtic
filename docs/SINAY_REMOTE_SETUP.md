# Sinay Design – bilgisayarsız ilk kurulum (Hostinger + GitHub Actions)

Hedef: Doğrudan https://sinaydesign.com üzerinde NEWTIC Setup Wizard açmak.

- Hedef Hostinger hesabı: u880685189
- Laravel dizini: ~/domains/sinaydesign.com/newtic
- Webroot: ~/domains/sinaydesign.com/public_html
- Veritabanı: u880685189_sinay_newtic
- GitHub ana dalı: kiddiewise/newtic/main
- Polaris'in mağazasına, ürünlerine veya veritabanına dokunulmaz.

## 1. GitHub Actions hazırlığı yapar

İlk kez main dalına eklenmesiyle başlayan workflow: .github/workflows/sinaydesign-first-setup.yml

Bir kurulum kodu 2 saat sonra geçersiz olursa telefondan yenilemek için:
1. https://github.com/kiddiewise/newtic/actions adresini aç.
2. Activate Sinay Design first-install wizard iş akışını seç.
3. Run workflow -> main -> Run workflow ile yeniden çalıştır.
4. Yeşil success durumunu doğrula.

İş akışı GitHub'da Node.js ile Vite CSS/JS derler, PHP/Laravel setup testlerini çalıştırır.
Önceden GitHub Secrets olarak tanımlı Hostinger SSH anahtarını kullanır.
Yalnızca Sinay'a ait domain/webroot, DB adı, güvenli ödeme bayrakları, Git commit
ve Composer paketleri doğrulandıktan sonra kurulum yapılır. Sunucuda Node gerekmez.

Güvenlik:
- Mevcut APP_KEY değiştirilmez.
- Herhangi bir eski mağaza verisi silinmez.
- Yeni yönetici veya müşteri otomatik oluşturulmaz; yalnız sihirbaz formundan yapılır.
- Hostinger PHP exec() çalışmıyorsa storage:link komutu çalıştırılmaz.
- 2 saatlik 64 karakterlik kurulum anahtarı GitHub loglarında, artifactlerinde veya URL'de gösterilmez.
- Anahtar yalnızca Hostinger hesabında gizli dosyada, 0600 izinleriyle saklanır.
- Dosya ilk kurulum başarıyla tamamlandığında silinir.
- İlk kurulum tamamlandıktan sonra yeniden token üretimi engellenir.

## 2. Telefon üzerinden özel anahtarı bul

1. Telefonda https://hpanel.hostinger.com/ adresinde oturum aç.
2. Web siteleri -> sinaydesign.com -> Dosyalar -> Dosya Yöneticisi bölümüne git.
3. Şu gizli dosyayı görüntüle:
   domains/sinaydesign.com/newtic/storage/app/private/newtic-setup/mobile-install-code.txt
4. Yalnız dosyadaki 64 karakterlik kodu kopyala.
5. Kodu sohbetlere, GitHub'a veya herkese açık başka bir yere gönderme.

Dosya, Laravel public_html klasörünün dışındadır. public_html/storage yalnız
storage/app/public klasörüne bağlıdır. Kod dosyası internetten doğrudan erişilemez.

## 3. Kurulum sihirbazını bitir

https://sinaydesign.com/install adresini aç.

1. Özel anahtarı gir.
2. PHP/HTTPS/MySQL/boş mağaza/ödeme test kontrollerinin başarılı olduğunu doğrula.
3. Mağaza adını Sinay Design yap. İlk yönetici adını, e-postasını ve güçlü şifreni yaz.
4. Test modu onayını kabul et ve kurulumu tamamla.

Başarılı tamamlanınca yeni yöneticiyle giriş yapılır. /install artık 404 döner.
Sinay ürünleri boş kalır; hiçbir Polaris katalog veya müşteri kaydı taşınmaz.

## 4. Son kontroller

- https://sinaydesign.com/ (mağaza vitrini)
- https://sinaydesign.com/admin/login (yönetici girişi)
- /install (kurulum tamamlandıktan sonra 404)
- Ödeme yalnız test modunda; PAYTR, gerçek kargo, pazarlama gönderimi kapalı
- SEO noindex; ürünler hazır olana kadar indeksleme açılmaz

Hata durumunda yalnız hata metnini paylaş, gizli kodu veya şifreyi paylaşma.
Dosya görünmüyorsa workflow henüz başarılı değildir ya da File Manager kök
klasörünün domains/ olarak açılması gerekir. Kod 2 saat sonra sona ererse
GitHub Actions -> Run workflow ile yenile.

Veritabanı şifresi önceki sohbette açığa çıktığı için Hostinger panelinden
değiştirilip sunucudaki özel .env dosyasında aynı yeni şifre kullanılmalıdır.
Şifre değişimi tamamlanmadan gerçek müşteri işlemlerini açmayın.

Bu iş akışı ilk kurulum için özeldir, genel müşteri sürüm yükseltme sistemi değildir.
