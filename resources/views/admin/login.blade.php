@extends('admin.layout')
@section('title', 'Yönetici Girişi | NEWTIC')
@section('content')
<div class="newtic-admin-signin" data-newtic-admin-signin>
  <section class="newtic-signin-side" aria-labelledby="newtic-signin-title">
    <div class="newtic-signin-wordmark"><span class="newtic-signin-symbol" aria-hidden="true"><svg viewBox="0 0 32 32" fill="none"><path d="M7 25V7l18 18V7" stroke="currentColor" stroke-width="3.6" stroke-linecap="round" stroke-linejoin="round"/><path d="M25 7h-5" stroke="#86b8e6" stroke-width="3.6" stroke-linecap="round"/></svg></span><span>NEWTIC<small>YÖNETİM PANELİ</small></span></div>
    <div class="newtic-signin-content">
      <div class="newtic-signin-store"><img src="{{ asset(\App\Models\ShopAppearance::current()->desktopLogo()) }}" alt="" width="128" height="40"><span>{{ \App\Support\StoreIdentity::name() }}</span></div>
      <h1 id="newtic-signin-title">Yönetim paneli</h1>
      <p class="newtic-signin-intro">Mağazanı yönetmek için hesabına giriş yap.</p>
      @if(session('success'))<div class="newtic-signin-notice" role="status">{{ session('success') }}</div>@endif
      @if($errors->any())<div class="newtic-signin-error" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
      <form action="{{ route('admin.login.submit') }}" method="POST" class="newtic-signin-form">
        @csrf
        <label for="newtic-admin-email">E-posta adresi</label>
        <div class="newtic-signin-field"><x-admin-icon name="mail"/><input id="newtic-admin-email" name="email" type="email" value="{{ old('email') }}" autocomplete="username" inputmode="email" autocapitalize="none" spellcheck="false" placeholder="ornek@magaza.com" required maxlength="255" @if($errors->has('email')) aria-invalid="true" @endif></div>
        <label for="newtic-admin-password">Şifre</label>
        <div class="newtic-signin-field"><svg class="admin-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 4v3" stroke-linecap="round"/></svg><input id="newtic-admin-password" name="password" type="password" autocomplete="current-password" placeholder="Şifrenizi girin" required @if($errors->has('password')) aria-invalid="true" @endif><button type="button" class="newtic-signin-eye" data-admin-password-toggle aria-controls="newtic-admin-password" aria-pressed="false" aria-label="Şifreyi göster" hidden><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 12s3.6-6 10-6 10 6 10 6-3.6 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/><path data-password-slash d="M4 4l16 16" hidden/></svg></button></div>
        <button type="submit" class="newtic-signin-submit"><span>Giriş yap</span><x-admin-icon name="arrow-right"/></button>
      </form>
      <p class="newtic-signin-private"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 4 6v6c0 5 4 8 8 9 4-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/></svg> Yalnızca yetkili yöneticiler için.</p>
    </div>
    <footer class="newtic-signin-footer"><span>© {{ now()->year }} NEWTIC</span><a href="{{ route('home') }}">Mağazaya dön <x-admin-icon name="external-link"/></a></footer>
  </section>
  <aside class="newtic-signin-visual" aria-label="NEWTIC mağaza yönetimi">
    <div class="newtic-signin-visual-top"><span>NEWTIC / COMMERCE</span><span>YÖNETİM</span></div>
    <div class="newtic-signin-visual-art" aria-hidden="true">
      <svg viewBox="0 0 500 350" fill="none" xmlns="http://www.w3.org/2000/svg">
        <defs><linearGradient id="nt-panel" x1="85" y1="45" x2="420" y2="310" gradientUnits="userSpaceOnUse"><stop stop-color="#355a7c"/><stop offset="1" stop-color="#112b44"/></linearGradient><linearGradient id="nt-line" x1="130" y1="240" x2="365" y2="122" gradientUnits="userSpaceOnUse"><stop stop-color="#79a8d7"/><stop offset="1" stop-color="#d5e8fc"/></linearGradient></defs>
        <circle cx="250" cy="173" r="163" stroke="#86b8e6" stroke-opacity=".13"/><circle cx="250" cy="173" r="130" stroke="#86b8e6" stroke-opacity=".13"/><path d="M40 173h420M250 8v330" stroke="#86b8e6" stroke-opacity=".10" stroke-dasharray="4 9"/>
        <rect x="79" y="53" width="342" height="241" rx="18" fill="#071b2d" opacity=".5" transform="translate(0 10)"/>
        <rect x="79" y="53" width="342" height="241" rx="18" fill="url(#nt-panel)" stroke="#7297b8" stroke-opacity=".55"/>
        <path d="M79 91h342M140 91v203" stroke="#9bbad6" stroke-opacity=".24"/><circle cx="98" cy="72" r="3" fill="#b8d4ed"/><circle cx="110" cy="72" r="3" fill="#7198b9"/><circle cx="122" cy="72" r="3" fill="#537795"/><rect x="157" y="69" width="67" height="6" rx="3" fill="#adc8df" opacity=".52"/>
        <rect x="94" y="110" width="32" height="29" rx="7" fill="#91b7d9" fill-opacity=".16"/><path d="M103 120h14m-14 5h14m-14 5h9" stroke="#bdd6ee" stroke-width="2" stroke-linecap="round"/><path d="M101 160h18m-18 25h18m-18 25h18m-18 25h18" stroke="#a0b9d0" stroke-opacity=".4" stroke-width="3" stroke-linecap="round"/>
        <rect x="157" y="107" width="114" height="61" rx="10" fill="#b0cde7" fill-opacity=".10" stroke="#a9c9e5" stroke-opacity=".22"/><rect x="283" y="107" width="121" height="61" rx="10" fill="#b0cde7" fill-opacity=".10" stroke="#a9c9e5" stroke-opacity=".22"/>
        <path d="M172 123h39m-39 14h56m-56 8h34M298 123h35" stroke="#bfd7ed" stroke-opacity=".55" stroke-width="3" stroke-linecap="round"/><path d="M303 151v-10m13 10v-15m13 15v-8m13 8v-23m13 23v-17m13 17v-27" stroke="#9fc9f0" stroke-width="5" stroke-linecap="round"/>
        <rect x="157" y="181" width="247" height="94" rx="10" fill="#09243c" fill-opacity=".7" stroke="#a9c9e5" stroke-opacity=".22"/><path d="M174 206h213m-213 20h213m-213 20h213" stroke="#c4d7e9" stroke-opacity=".12"/><path d="m175 252 35-12 35 4 37-25 34 9 34-23 37-8" stroke="url(#nt-line)" stroke-width="3" stroke-linejoin="round" stroke-linecap="round"/>
        <g transform="translate(42 221)"><rect width="85" height="77" rx="15" fill="#e6eff8"/><path d="m24 28 18-10 18 10v20L42 58 24 48zM24 28l18 10 18-10M42 38v20" stroke="#315e85" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></g>
        <g transform="translate(385 29)"><rect width="74" height="72" rx="15" fill="#dceaf7"/><path d="M24 26h26v30H24zM31 26v-5a6 6 0 0 1 12 0v5" stroke="#315e85" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><circle cx="57" cy="55" r="10" fill="#315e85"/><path d="m53 55 3 3 5-6" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></g>
      </svg>
    </div>
    <div class="newtic-signin-visual-copy"><span>TEK MERKEZDEN YÖNETİM</span><h2>Mağazan.<br>Senin kontrolünde.</h2><p>Siparişlerden stok takibine, mağazanın tüm işleyişi bir arada.</p><div class="newtic-signin-visual-features"><span><x-admin-icon name="bag"/> Siparişler</span><span><x-admin-icon name="package"/> Ürünler</span><span><x-admin-icon name="chart"/> Analizler</span></div></div>
  </aside>
</div>
@endsection
