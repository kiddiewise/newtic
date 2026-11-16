# Polaris Kurşun - NEWTIC yasal sayfa veri aktarımı

Kaynaklar (16 Kasım 2026):
- https://polariskursun.com/hakkimizda/
- https://polariskursun.com/iletisim/
- https://polariskursun.com/gizlilik-politikasi/
- https://polariskursun.com/kisisel-verilere-iliskin-aydinlatma-metni/
- https://polariskursun.com/uyelik-sozlesmesi/
- https://polariskursun.com/sartlar-ve-kosullar/
- https://polariskursun.com/mesafeli-satis-sozlesmesi/ (403 döndü; şartlar sayfası kullanıldı)
- https://polariskursun.com/iptal-ve-iade-kosullari/
- https://polariskursun.com/sikca-sorulan-sorular/

## Kullanılan işletme bilgileri

KVKK metnindeki satıcı/veri sorumlusu: ZOKA BALIK AV MALZEMELERİ MÜCAHİT MEYDAN.
Marka: Polaris Kurşun. Adres: Atilla Mahallesi, İnönü Caddesi No:39/A, Konak/İzmir.
Telefon: 0546 262 90 02. Hukukî bildirim e-postası: bilgi@polariskursun.com.

Açık kaynakta posta kodları 35260 ve 35270 şeklinde çeliştiğinden posta kodu
çıkarıldı. İletişim sayfası site@polariskursun.com, KVKK ve iade sayfaları
bilgi@polariskursun.com gösteriyor. Vergi no, KEP, MERSİS ve çalışma saatleri
doğrulanamadı, bilerek boş kaldı.

## NEWTIC'e uyarlanan 10 sayfa

Hakkımızda, İletişim, Gizlilik Politikası, KVKK Aydınlatma Metni,
Üyelik Koşulları, Mesafeli Satış Sözleşmesi, Ön Bilgilendirme Formu,
Teslimat ve İade Şartları, Çerez Politikası, İşlem Rehberi.

Özellikle Ön Bilgilendirme, Çerez ve İşlem Rehberi sayfaları NEWTIC
iş akışından hareketle yeni hazırlandı; eskisinden doğrudan aktarım
değildir. Metinler yeni sisteme uyarlama niteliğindedir, hukukî onay
anlamına gelmez.

## Bilerek aktarılmayan footer verileri

- ETBİS logosu: Kaynaktaki resmi ETBİS doğrulama linki PageNotFound
  sonucuna yönlendi; kayıt tekrar doğrulanmadan bir güven iddiası yapılmaz.
- TROY ve diğer ödeme logoları: kullanım yetkisi ve canlı ödeme onayı
  doğrulanmadı; banka/kart logoları eklenmedi.
- Kaynaktaki IBAN: eski sitede herkese açık olsa da yanlış tahsilat
  riskine karşı NEWTIC banka hesapları değiştirilmedi.
- Kargo fiyatı/eşik: eski SSS fiyatları NEWTIC kargo ayarlarından farklı
  olabileceğinden taşınmadı; aktif sepet fiyatları esas.
- Eski tek kapsamlı KVKK rıza metni: NEWTIC üye/satış sözleşmesi,
  aydınlatma, çerez ve ticari ileti/IYS izinlerini ayrı tutar.

## Dağıtım güvenliği

Hedef yalnız Polaris geçici test sitesi:
https://powderblue-dinosaur-538060.hostingersite.com

Komut: php artisan newtic:polaris-legal --publish

Komut Hostinger dizini, domain ve test ödeme modu dışında çalışmayı
reddeder. Mevcut yönetici metinleri ve kimlik bilgilerini ezmez.
Kaynak WordPress, polariskursun.com ve Sinay Design verilerine yazmaz.

## Satıcı tarafından kontrol edilmesi gereken bilgiler

Güncel resmî ticari unvan ve adres/posta kodu, hukukî e-posta
teslim edilebilirliği, KEP/MERSİS/vergi alanları, kargo ve iade
taşıyıcısı, ürün bazında cayma istisnaları ve alıcıya özel
teslim tarihi, ETBİS/ödeme markası onayları, üçüncü taraf çerezler,
İYS ve veri aktarımı/saklama gerçek işletime göre gözden geçirilmeli.
Bu inceleme yapılmadan gerçek tahsilata geçilmemelidir.
