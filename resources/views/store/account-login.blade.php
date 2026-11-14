@extends('layouts.store')
@section('title', 'Üye Girişi | {{ \App\Support\StoreIdentity::name() }}')
@section('content')
@php
  $authNext = in_array(request('next'), ['checkout', 'wishlist', 'cart', 'review'], true) ? request('next') : '';
  $favoriteId = ctype_digit((string) request('favorite', '')) ? request('favorite') : '';
  $reviewProduct = $authNext === 'review' ? mb_substr((string) request('review_product', ''), 0, 200) : '';
  $authParams = array_filter(['next' => $authNext, 'favorite' => $authNext === 'cart' ? $favoriteId : '', 'review_product' => $authNext === 'review' ? $reviewProduct : '']);
@endphp
<main class="checkout-shell account-auth-shell" id="content">
  <div class="auth-experience">
    @include('store.partials.auth-artwork')
    <div class="auth-form-column">
      @include('store.partials.mobile-auth-navigation')
      <div class="checkout-card auth-card">
        <div class="auth-form-heading">
          <span class="auth-eyebrow">HESABINA HOŞ GELDİN</span>
          <h1>Tekrar hoş geldin<span>.</span></h1>
          <p>{{ $authNext === 'cart' && $favoriteId ? 'Bu ürünü favorilerine eklemek için giriş yap.' : ($authNext === 'wishlist' ? 'Favorilerini görmek için giriş yap.' : ($authNext === 'review' ? 'Deneyimini paylaşmak için hesabına giriş yap.' : 'Siparişlerini, favorilerini ve adreslerini tek yerden yönet.')) }}</p>
        </div>
        @if($errors->any())<div class="checkout-error auth-error" role="alert">{{ $errors->first() }}</div>@endif
        @include('store.partials.social-auth')
        <form method="POST" action="{{ route('account.login.submit') }}" class="checkout-form auth-fields">
          @csrf
          <input type="hidden" name="next" value="{{ $authNext }}">
          @if($authNext === 'review')<input type="hidden" name="review_product" value="{{ $reviewProduct }}">@endif
          @if($authNext === 'cart' && $favoriteId)<input type="hidden" name="favorite" value="{{ $favoriteId }}">@endif
          <label class="checkout-field"><span>E-posta adresi</span><input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email" placeholder="ornek@eposta.com"></label>
          <label class="checkout-field"><span>Şifre</span><input name="password" type="password" required autocomplete="current-password" placeholder="Şifreni gir"></label>
          <button class="checkout-action auth-submit" type="submit">Giriş yap <span aria-hidden="true">→</span></button>
        </form>
        <p class="checkout-secondary auth-switch">Hesabın yok mu? <a href="{{ route('account.register', $authParams) }}">Hemen üye ol</a></p>
        <div class="auth-help"><span>Güvenli alışveriş</span><span aria-hidden="true">•</span><span>Kolay sipariş takibi</span></div>
      </div>
    </div>
  </div>
</main>
@endsection