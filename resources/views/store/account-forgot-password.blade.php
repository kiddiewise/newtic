@extends('layouts.store')
@section('title', 'Şifremi Unuttum | {{ \App\Support\StoreIdentity::name() }}')
@section('content')
@php
  $authNext = '';
  $favoriteId = '';
  $reviewProduct = '';
@endphp
<main class="checkout-shell account-auth-shell" id="content">
  <div class="auth-experience">
    @include('store.partials.auth-artwork')
    <div class="auth-form-column">
      @include('store.partials.mobile-auth-navigation')
      <div class="checkout-card auth-card">
        <div class="auth-form-heading">
          <span class="auth-eyebrow">HESAP GÜVENLİĞİ</span>
          <h1>Şifreni yenile<span>.</span></h1>
          <p>Hesabına ait e-posta adresini gir. Kayıtlı bir hesabın varsa güvenli yenileme bağlantısı göndereceğiz.</p>
        </div>
        @if(session('success'))<div class="checkout-success" role="status">{{ session('success') }}</div>@endif
        @if($errors->any())<div class="checkout-error auth-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('account.password.send') }}" class="checkout-form auth-fields">
          @csrf
          <label class="checkout-field"><span>E-posta adresi</span>
            <input name="email" type="email" required maxlength="255" autocomplete="email"
              value="{{ old('email') }}" placeholder="ornek@eposta.com"></label>
          <button class="checkout-action auth-submit" type="submit">Güvenli bağlantı gönder</button>
        </form>
        <p class="checkout-secondary auth-switch"><a href="{{ route('account.login') }}">Giriş ekranına dön</a></p>
      </div>
    </div>
  </div>
</main>
@endsection
