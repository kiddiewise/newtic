@extends('layouts.store')
@section('title', 'Üye Ol | Polaris Kurşun')
@section('content')
<main class="checkout-shell account-auth-shell" id="content">
  <div class="checkout-top"><a href="{{ route('account.login') }}" aria-label="Geri dön">‹</a><strong>Üyelik Oluştur</strong><span></span></div>
  <div class="checkout-card">
    <h1>Polaris hesabını oluştur</h1><p>Birden fazla adres kaydet, geçmiş siparişlerini takip et.</p>
    @include('store.partials.social-auth')
    <form method="POST" action="{{ route('account.register.submit') }}" class="checkout-form">
      @csrf
      <input type="hidden" name="next" value="{{ request('next') === 'checkout' ? 'checkout' : '' }}">
      <label class="checkout-field"><span>Ad Soyad *</span><input name="name" type="text" maxlength="160" autocomplete="name" required value="{{ old('name') }}"></label>
      <label class="checkout-field"><span>E-posta *</span><input name="email" type="email" maxlength="255" autocomplete="email" required value="{{ old('email') }}"></label>
      <label class="checkout-field"><span>Şifre (en az 8 karakter) *</span><input name="password" type="password" minlength="8" autocomplete="new-password" required></label>
      <label class="checkout-field"><span>Şifre tekrarı *</span><input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required></label>
      @if($errors->any())<div class="checkout-error" role="alert">{{ $errors->first() }}</div>@endif
      <button class="checkout-action" type="submit">Hesabımı oluştur</button>
    </form>
  </div>
</main>
@endsection
