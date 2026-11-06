<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="robots" content="noindex,nofollow,noarchive">
  <title>@yield('title', 'NEWTIC Yönetim Paneli')</title>
  @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="admin-body">
  <header class="admin-topbar">
    <div class="admin-topbar-inner">
      <a href="{{ route('admin.index') }}" class="admin-brand"><span class="admin-brand-mark">N</span><span>NEWTIC <small>Yönetim Paneli</small></span></a>
      <div class="admin-top-actions">
        <a href="{{ route('home') }}" target="_blank" rel="noopener noreferrer">Mağazayı gör ↗</a>
        @auth
        <form action="{{ route('admin.logout') }}" method="POST">@csrf<button type="submit" class="admin-logout">Çıkış yap</button></form>
        @endauth
      </div>
    </div>
  </header>
  <main class="admin-main">
    @if(session('success'))<div class="admin-alert admin-alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())
      <div class="admin-alert admin-alert-error" role="alert">
        <strong>Kaydedilemedi:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif
    @yield('content')
  </main>
  <footer class="admin-footer">NEWTIC · Polaris Kurşun · Geliştirme paneli</footer>
</body>
</html>
