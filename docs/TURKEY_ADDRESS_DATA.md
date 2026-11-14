# Türkiye il–ilçe adres veri kaynağı

NEWTIC'in il–ilçe listesini kaynak kodla beraber **yerel** tutuyoruz. Çalışma zamanında üçüncü taraf API'ye istek gönderilmez.

- Kaynak: [mumeyyizbuyuk/turkiye-data](https://github.com/mumeyyizbuyuk/turkiye-data)
- İl kaynak dosyası: `data/provinces.json`
- İlçe kaynak dosyası: `data/districts.json`
- Kaynak sürümü: 2025 veri seti; 81 il ve 973 ilçe. NEWTIC'e alınış tarihi 12.11.2026.
- Yerel liste: `resources/data/turkey-districts.json` (yalnızca il ve ilçe adları; nüfus, telefon veya kişisel veri içermez).
- Lisans: MIT License. İlçeler PTT'nin 2026 genel sayı bilgisiyle karşılaştırılmıştır; resmî idari değişikliklerde liste yeniden gözden geçirilmelidir.

## MIT License – source attribution

Copyright (c) 2026 Kaan Apak Altıntop

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

## Validation policy

- İlce tek başına serbest metin değildir; ilgili ilin listesinde olmalıdır.
- İl değişince eski ilçe temizlenir.
- Chrome otomatik doldurma veya eski kayıt hatalı ilçe getirdiğinde form seçimi boş kalır ve tekrar seçim istenir.
- Laravel hem ödeme siparişinde hem adres defteri ekleme/düzenlemede aynı listeyle doğrulama yapar.
- Eski sipariş/adresler topluca değiştirilmez; sadece yeni kayıt ve kullanıcı düzenlemesi doğrulanır.
