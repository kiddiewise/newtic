@extends('setup.layout')
@section('title','Mağaza ve yönetici hesabı')
@section('content')
  <div class="eyebrow">3 / 3 · İLK MAĞAZA HESABI</div>
  <h1>Mağazanızı oluşturalım.</h1>
  <p>İlk yönetici hesabınız sadece bu mağazaya ait olacak. Başka mağazaların ürünleri veya müşteri bilgileri kopyalanmaz.</p>
  <div class="steps"><span class="active"></span><span class="active"></span><span class="active"></span></div>
  <div class="notice">Domain: <strong>{{ $domain }}</strong> · İlk açılışta SEO indeksleme ve gerçek tahsilat kapalı kalır.</div>
  <form method="post" action="{{ route('install.finish') }}">
    @csrf
    <label for="store_name">Mağaza adı *</label>
    <input id="store_name" name="store_name" required maxlength="120" minlength="2"
      placeholder="Örneğin Sinay Design" value="{{ old('store_name', config('app.name')) }}">
    <div class="grid">
      <div><label for="admin_name">Yönetici adı *</label>
        <input id="admin_name" name="admin_name" required minlength="3" maxlength="160"
          placeholder="Ad Soyad" autocomplete="name" value="{{ old('admin_name') }}"></div>
      <div><label for="admin_email">Yönetici e-posta *</label>
        <input id="admin_email" name="admin_email" type="email" required maxlength="255"
          autocomplete="email" placeholder="yonetici@magaza.com" value="{{ old('admin_email') }}"></div>
      <div><label for="password">Güçlü yönetici şifresi *</label>
        <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password">
        <small>En az 12 karakter, büyük/küçük harf, rakam ve simge.</small></div>
      <div><label for="password_confirmation">Şifre tekrar *</label>
        <input id="password_confirmation" name="password_confirmation" type="password"
          required minlength="12" autocomplete="new-password"></div>
    </div>
    <label class="check-label">
      <input type="checkbox" name="accept_safe_mode" value="1" required>
      <span>Mağazanın ilk açılışta arama motorlarına kapalı olacağını ve ödeme ile kargo hizmetlerini kendi firma bilgilerimle yapılandırıp doğruladıktan sonra etkinleştireceğimi kabul ediyorum.</span>
    </label>
    <div class="actions">
      <a href="{{ route('install.checks') }}" style="color:#092037">Önceki adıma dön</a>
      <button type="submit">Güvenli kurulumu tamamla</button>
    </div>
  </form>
@endsection
