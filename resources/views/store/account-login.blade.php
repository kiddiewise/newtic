@extends('layouts.store')
@section('title', 'Üye Girişi | Polaris Kurşun')
@section('content')
@php
  $authNext = in_array(request('next'), ['checkout', 'wishlist', 'cart', 'review'], true) ? request('next') : '';
  $favoriteId = ctype_digit((string) request('favorite', '')) ? request('favorite') : '';
  $reviewProduct = $authNext === 'review' ? mb_substr((string) request('review_product', ''), 0, 200) : '';
@endphp
<main class="checkout-shell account-auth-shell" id="content">
  @include('store.partials.mobile-auth-navigation')
  <div class="checkout-top"><a href="{{ $authNext === 'cart' ? route('cart.index') : ($authNext === 'wishlist' ? route('cart.index') : route('checkout.index')) }}" aria-label="Geri dön">‹</a><strong>Üye Girişi</strong><span></span></div>
  <div class="checkout-card">
    <h1>Tekrar hoş geldin</h1><p>{{ $authNext === 'cart' && $favoriteId ? 'Bu ürünü favorilerine eklemek için üye girişi yap.' : ($authNext === 'wishlist' ? 'Favorilerini görmek için üye girişi yap.' : ($authNext === 'review' ? 'Ürün deneyimini paylaşmak için giriş yap.' : 'Siparişlerini ve kayıtlı adreslerini hesabından yönet.')) }}</p>
    @include('store.partials.social-auth')
    <form method="POST" action="{{ route('account.login.submit') }}" class="checkout-form">
      @csrf
      <input type="hidden" name="next" value="{{ $authNext }}">
      @if($authNext === 'review')<input type="hidden" name="review_product" value="{{ $reviewProduct }}">@endif
      @if($authNext === 'cart' && $favoriteId)<input type="hidden" name="favorite" value="{{ $favoriteId }}">@endif
      <label class="checkout-field"><span>E-posta *</span><input name="email" type="email" value="{{ old('email') }}" required autocomplete="email"></label>
      <label class="checkout-field"><span>Şifre *</span><input name="password" type="password" required autocomplete="current-password"></label>
      @if($errors->any())<div class="checkout-error" role="alert">{{ $errors->first() }}</div>@endif
      <button class="checkout-action" type="submit">Giriş yap</button>
    </form>
    <p class="checkout-secondary">Hesabın yok mu? <a href="{{ route('account.register', array_filter(['next' => $authNext, 'favorite' => $authNext === 'cart' ? $favoriteId : '', 'review_product' => $authNext === 'review' ? $reviewProduct : ''])) }}">Üye ol</a></p>
  </div>
</main>
@endsection
