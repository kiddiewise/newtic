<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <title>Yönetici Hesabını Kurtar · NEWTIC</title>
  <style>
    *{box-sizing:border-box}body{margin:0;background:#f3f6f8;color:#092037;
      font:15px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
    main{max-width:510px;margin:clamp(24px,8vh,90px) auto;padding:0 18px}
    article{background:white;border:1px solid #dce7ed;border-radius:20px;
      box-shadow:0 20px 55px #09203712;padding:clamp(24px,6vw,36px)}
    h1{margin:10px 0;font-size:clamp(25px,5vw,32px);letter-spacing:-1px;line-height:1.2}
    p,small{color:#607380}.eyebrow{font-size:11px;letter-spacing:2px;font-weight:700;color:#427e82}
    label{display:block;font-size:13px;font-weight:650;margin-top:17px}
    input{width:100%;height:49px;padding:12px;border:1px solid #cfdee4;
      border-radius:10px;font:inherit;margin-top:6px}
    input:focus{outline:2px solid #8baeb8}
    button{width:100%;border:0;background:#092037;color:white;border-radius:11px;
      padding:14px;font-size:15px;font-weight:700;cursor:pointer;margin-top:24px}
    .error{background:#fff1f2;color:#9d2430;border:1px solid #f4cccc;
      padding:13px;border-radius:9px;margin:18px 0}
    .note{background:#edf4f6;padding:12px 15px;border-radius:10px;margin:18px 0}
    a{color:#092037}
  </style>
</head>
<body>
<main>
  <article>
    <div class="eyebrow">NEWTIC · GÜVENLİ HESAP KURTARMA</div>
    <h1>Yönetici şifrenizi yenileyin.</h1>
    <p>Hostinger dosya yöneticisindeki özel kurtarma kodunu girerek mevcut yönetici hesabınız için yeni bir şifre belirleyin.</p>
    <div class="note"><small>Bu ekran yalnızca sunucu sahibi tarafından özel olarak etkinleştirildiğinde açılır. Kod 30 dakika geçerlidir ve yalnızca bir kez kullanılabilir.</small></div>
    @if($errors->any())
      <div class="error" role="alert">{{ $errors->first() }}</div>
    @endif
    <form method="POST" action="{{ route('admin.recovery.reset') }}" autocomplete="off">
      @csrf
      <label for="recovery_code">Özel kurtarma kodu</label>
      <input id="recovery_code" type="password" name="recovery_code"
             required minlength="32" maxlength="128" autocomplete="off" spellcheck="false" autofocus>
      <label for="password">Yeni güçlü şifre</label>
      <input id="password" type="password" name="password"
             required minlength="12" autocomplete="new-password">
      <small>En az 12 karakter, büyük-küçük harf, sayı ve özel işaret.</small>
      <label for="password_confirmation">Yeni şifre tekrar</label>
      <input id="password_confirmation" type="password" name="password_confirmation"
             required minlength="12" autocomplete="new-password">
      <button type="submit">Şifremi güvenli şekilde yenile</button>
    </form>
    <p><small>Şifreyi değiştirdiğinizde eski oturumlarınız kapatılır. Yeni şifrenizi yalnızca güvenli yerde saklayın.</small></p>
  </article>
</main>
</body>
</html>
