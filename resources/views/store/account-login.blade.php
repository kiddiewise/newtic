@extends('layouts.store')
@section('title', 'Üye Girişi | Polaris Kurşun')
@section('content')
<main class="checkout-shell account-auth-shell" id="content">
  <div class="checkout-top"><a href="{{ route('checkout.index') }}" aria-label="Geri dön">‹</a><strong>Üye Girişi</strong><span></span></div>
  <div class="checkout-card">
    <h1>Tekrar hoş geldin</h1><p>Siparişlerini ve kayıtlı adreslerini hesabından yönet.</p>
    <form method="POST" action="{{ route('account.login.submit') }}" class="checkout-form">
      @csrf
      <input type="hidden" name="next" value="{{ request('next') === 'checkout' ? 'checkout' : '' }}">
      <label class="checkout-field"><span>E-posta *</span><input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
      <label class="checkout-field"><span>Şifre *</span><input name="password" type="password" required autocomplete="current-password"></label>
      @if($errors->any())<div class="checkout-error" role="alert">{{ $errors->first() }}</div>@endif
      <button class="checkout-action" type="submit">Giriş yap</button>
    </form>
    <p class="checkout-secondary">Hesabın yok mu? <a href="{{ route('account.register', ['next' => request('next') === 'checkout' ? 'checkout' : null]) }}">Üye ol</a></p>
  </div>
</main>
@endsection
