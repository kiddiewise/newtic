@extends('admin.layout')
@section('title', 'Yönetici Girişi | NEWTIC')
@section('content')
  <div class="admin-login-shell">
    <div class="admin-panel admin-login-panel">
      <div class="admin-login-brand"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="bag" /></span><div><div class="admin-eyebrow">NEWTIC · POLARIS KURŞUN</div><strong>Yönetim Paneli</strong></div></div>
      <h1>Yönetim paneli</h1>
      <p class="admin-description">Vitrin bannerlarını, kategorileri ve ürünleri yönetin.</p>
      <form action="{{ route('admin.login.submit') }}" method="POST" class="admin-form">
        @csrf
        <label>E-posta adresi<input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required maxlength="255"></label>
        <label>Şifre<input name="password" type="password" autocomplete="current-password" required></label>
        <button type="submit" class="admin-primary">Giriş yap</button>
      </form>
      <p class="admin-description admin-muted-note">İlk yönetici kullanıcı sunucuda <code>php artisan newtic:admin</code> komutuyla oluşturulur.</p>
    </div>
  </div>
@endsection
