# NEWTIC Yönetim PWA
- HTTPS altında /admin/dashboard başlangıç ve /admin/ kapsamı. Admin hesabı ile giriş gerekir.
- Daha Fazla > Mobil Uygulama ve Bildirimler. Android Chrome: Uygulamayı yükle; iOS/iPadOS 16.4+ Safari: Ana Ekrana Ekle, ardından ana ekrandan aç ve bildirim iznini ver.
- Web Push için mağazaya özel P-256 VAPID anahtarı şifreli saklanır. Sadece izinli sağlayıcılara, yönlendirmesiz, boş gövdeli istek gönderilir. Kilit ekranında kişisel sipariş verisi yoktur.
- Laravel scheduler her dakika admin:push-orders çalıştırır. Cron ve dış HTTPS bağlantısı gereklidir; dağıtım sonrası gerçek telefon testi yapılmalıdır.
- Yeni abonelik eski siparişlerle uyarı yağmuruna tutulmaz. Çıkış, bu oturumun aboneliğini siler; abonelik 30 gün sonra sona erer. Çevrimdışı özel veri saklanmaz.
- Test bildirimi sağlayıcının kabulünü kontrol eder, teslim veya ses garantisi değildir. Sessiz mod ve işletim sistemi izinleri geçerlidir.
