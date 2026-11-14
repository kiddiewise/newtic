@extends('layouts.store')
@section('title', 'Yeni Şifre | {{ \App\Support\StoreIdentity::name() }}')
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
          <h1>Yeni şifreni belirle<span>.</span></h1>
          <p>Yeni şifren en az sekiz karakter olmalıdır. Bağlantı tek kullanımlıktır.</p>
        </div>
        @if($errors->any())<div class="checkout-error auth-error" role="alert">{{ $errors->first() }}</div>@endif
        <form method="POST" action="{{ route('account.password.update') }}" class="checkout-form auth-fields">
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">
          <label class="checkout-field"><span>E-posta adresi</span><input type="email" name="email" value="{{ old('email',$email) }}" required maxlength="255" autocomplete="email"></label>
          <label class="checkout-field"><span>Yeni şifre</span><input type="password" name="password" required minlength="8" autocomplete="new-password"></label>
          <label class="checkout-field"><span>Yeni şifre tekrar</span><input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"></label>
          <button class="checkout-action auth-submit" type="submit">Şifremi kaydet</button>
        </form>
        <p class="checkout-secondary auth-switch"><a href="{{ route('account.login') }}">Giriş ekranına dön</a></p>
      </div>
    </div>
  </div>
</main>
@endsection
