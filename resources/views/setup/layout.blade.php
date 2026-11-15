<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <meta name="referrer" content="no-referrer">
  <title>@yield('title', 'Yeni mağaza kurulumu') · NEWTIC</title>
  <style>
    :root { color-scheme: light; }
    * { box-sizing: border-box; }
    body { margin: 0; color:#092037; background:linear-gradient(145deg,#eff4f6,#f8fafb 66%,#e8eff4);
      font: 15px/1.55 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif; min-height:100vh; }
    .container { width: min(100% - 32px, 760px); margin: 38px auto 60px; }
    .brand { display:flex;align-items:center;justify-content:space-between; margin-bottom:22px; }
    .brand strong { font-size:21px;letter-spacing:-.8px; }
    .tag { border:1px solid #d8e3e9;border-radius:99px;padding:6px 12px;font-size:12px;letter-spacing:.4px; }
    .panel { background:white;border:1px solid #e0e7ec;border-radius:24px;
      padding:clamp(20px,5vw,38px);box-shadow:0 20px 60px rgba(9,32,55,.07); }
    .eyebrow {font-weight:700;font-size:11px;letter-spacing:1.2px;text-transform:uppercase;color:#56818a;}
    h1 { font-size:clamp(26px,4vw,38px);line-height:1.2;letter-spacing:-1.3px;margin:12px 0; }
    h2 {font-size:18px;letter-spacing:-.4px;}
    p,small {color:#576b7c;}
    label {display:block;font-weight:650;font-size:13px;margin:18px 0 7px;}
    input {width:100%;min-height:49px;padding:12px 14px;border:1px solid #d8e3e9;
      border-radius:10px;background:#f9fbfc;color:#092037;font:inherit;}
    input:focus {outline:2px solid #9bb9c4;outline-offset:1px;}
    button,.button { background:#092037;color:white;text-decoration:none;border:0;border-radius:11px;
      min-height:47px;padding:12px 21px;font-weight:700;display:inline-flex;align-items:center;justify-content:center;
      cursor:pointer;font-size:14px;}
    button:hover,.button:hover {background:#173a56;}
    button:disabled {opacity:.5;cursor:not-allowed;}
    .disabled {opacity:.5;cursor:not-allowed;pointer-events:none;}
    .grid {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 17px;}
    .notice {background:#f1f6f9;border:1px solid #d7e5eb;color:#244a60;
      border-radius:11px;padding:13px 16px;margin:18px 0;}
    .errors {background:#fff2f2;border:1px solid #f5cccc;color:#a52e39;
      border-radius:11px;padding:12px 17px;margin:18px 0;}
    .errors ul {margin:0;padding-left:17px;}
    .steps {display:flex;gap:6px;margin:21px 0 26px;}
    .steps span {height:5px;background:#dae4eb;flex:1;border-radius:99px;}
    .steps .active {background:#092037;}
    .checks {list-style:none;padding:0;margin:20px 0;border:1px solid #e8edf1;border-radius:13px;overflow:hidden;}
    .checks li {display:flex;align-items:center;justify-content:space-between;gap:14px;
      padding:12px 15px;border-bottom:1px solid #e8edf1;font-size:14px;}
    .checks li:last-child {border:0;}
    .success {color:#287557;font-weight:750;white-space:nowrap;}
    .failed {color:#ad3545;font-weight:750;white-space:nowrap;}
    .actions {display:flex;justify-content:space-between;align-items:center;gap:15px;flex-wrap:wrap;margin-top:26px;}
    .foot {margin-top:20px;text-align:center;color:#8393a1;font-size:12px;}
    .check-label {display:flex;align-items:flex-start;gap:11px;font-weight:400;}
    .check-label input {width:18px;height:18px;min-height:auto;flex-shrink:0;margin-top:3px;}
    @media(max-width:560px){.container{margin-top:18px}.grid{grid-template-columns:1fr}.panel{border-radius:16px}}
  </style>
</head>
<body>
  <div class="container">
    <div class="brand">
      <strong>NewTic<span style="color:#6b9fa2">.</span></strong>
      <span class="tag">GÜVENLİ MAĞAZA KURULUMU</span>
    </div>
    <main class="panel">
      @if($errors->any())
        <div class="errors" role="alert"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
      @endif
      @yield('content')
    </main>
    <p class="foot">NEWTIC Kurulum Sihirbazı v1.0 · İlk kurulumdan sonra erişim otomatik kapanır.</p>
  </div>
</body>
</html>
