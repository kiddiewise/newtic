@extends('admin.layout')
@section('title', 'Yönetici Girişi | NEWTIC')
@section('content')
  <div class="admin-login-shell">
    <div class="admin-panel admin-login-panel">
      <div class="admin-login-brand">
        <img class="admin-login-brand-logo" src="{{ asset(\App\Models\ShopAppearance::current()->desktopLogo()) }}" alt="{{ \App\Support\StoreIdentity::name() }} logosu" width="192" height="48">
        <div><div class="admin-eyebrow">{{ \App\Support\StoreIdentity::name() }}</div><strong>NEWTIC Yönetim Paneli</strong></div>
      </div>
      <h1>Yönetim paneli</h1>
      @if(session('success'))<div class="admin-alert admin-alert-success" role="status">{{ session('success') }}</div>@endif
      <p class="admin-description">Sipariş, ödeme bildirimi, ürün, stok ve mağaza yönetimi tek kontrol panelinde.</p>
      <form action="{{ route('admin.login.submit') }}" method="POST" class="admin-form">
        @csrf
        <label>E-posta adresi<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="255"></label>
        <label>Şifre<input name="password" type="password" autocomplete="current-password" required></label>
        <button type="submit" class="admin-primary">Giriş yap</button>
      </form>
    </div>
  </div>
@endsection
