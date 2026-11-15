@extends('layouts.store')
@section('title', 'Üye Ol | {{ \App\Support\StoreIdentity::name() }}')
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
          <span class="auth-eyebrow">YENİ BİR BAŞLANGIÇ</span>
          <h1>Aramıza katıl<span>.</span></h1>
          <p>{{ $authNext === 'cart' && $favoriteId ? 'Ücretsiz hesabını aç, seçtiğin ürünü favorilerine ekleyelim.' : 'Yeni ürünleri keşfet, favorilerini sakla ve siparişlerini kolayca takip et.' }}</p>
        </div>
        @if($errors->any())<div class="checkout-error auth-error" role="alert">{{ $errors->first() }}</div>@endif
        @include('store.partials.social-auth')
        <form method="POST" action="{{ route('account.register.submit') }}" class="checkout-form auth-fields">
          @csrf
          <input type="hidden" name="next" value="{{ $authNext }}">
          @if($authNext === 'review')<input type="hidden" name="review_product" value="{{ $reviewProduct }}">@endif
          @if($authNext === 'cart' && $favoriteId)<input type="hidden" name="favorite" value="{{ $favoriteId }}">@endif
          <label class="checkout-field"><span>Ad Soyad</span><input name="name" type="text" maxlength="160" autocomplete="name" required value="{{ old('name') }}" placeholder="Adın ve soyadın"></label>
          <label class="checkout-field"><span>E-posta adresi</span><input name="email" type="email" maxlength="255" autocomplete="email" required value="{{ old('email') }}" placeholder="ornek@eposta.com"></label>
          <label class="checkout-field"><span>Şifre (en az 8 karakter)</span><input name="password" type="password" minlength="8" autocomplete="new-password" required placeholder="Güçlü bir şifre oluştur"></label>
          <label class="checkout-field"><span>Şifre tekrarı</span><input name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required placeholder="Şifreni tekrar gir"></label>
          <label class="checkout-check">
            <input type="checkbox" name="marketing_opt_in" value="1" @checked(old('marketing_opt_in'))>
            <span><strong>İsteğe bağlı kampanya ve fırsat e-postalarını almak istiyorum.</strong>
              <small>Üyelik için zorunlu değildir. İstediğim zaman ücretsiz çıkabilirim; İYS onayı ayrıca doğrulanır.</small></span>
          </label>
          <button class="checkout-action auth-submit" type="submit">Hesabımı oluştur <span aria-hidden="true">→</span></button>
        </form>
        <p class="checkout-secondary auth-switch">Zaten üye misin? <a href="{{ route('account.login', $authParams) }}">Giriş yap</a></p>
        <div class="auth-help"><span>Hızlı üyelik</span><span aria-hidden="true">•</span><span>Güvenli alışveriş</span></div>
      </div>
    </div>
  </div>
</main>
@endsection