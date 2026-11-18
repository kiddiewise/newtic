@php
  $authAppearance = \App\Models\ShopAppearance::current();
  $authArtwork = array_values((array) ($authAppearance->auth_image_paths ?? []));
  $storeBrand = \App\Support\StoreIdentity::name();
@endphp
<aside class="auth-artwork" aria-label="{{ $storeBrand }} hoş geldiniz görselleri">
  @if($authArtwork)
  <div class="auth-artwork-slides" data-auth-slideshow data-slide-delay="6200">
    @foreach($authArtwork as $index => $path)
      <div class="auth-artwork-slide {{ $index === 0 ? 'is-active' : '' }}" data-auth-slide aria-hidden="{{ $index === 0 ? 'false' : 'true' }}">
        <img src="{{ asset($path) }}" alt="{{ $storeBrand }} görseli {{ $index + 1 }}"
          width="1600" height="2000" decoding="async"
          @if($index > 0) loading="lazy" @else fetchpriority="high" @endif>
      </div>
    @endforeach
  </div>
  @else
  <div class="auth-artwork-fallback" aria-hidden="true">
    <span class="auth-orb auth-orb-one"></span>
    <span class="auth-orb auth-orb-two"></span>
    <span class="auth-orb auth-orb-three"></span>
    <span class="auth-orbit auth-orbit-one"></span>
    <span class="auth-orbit auth-orbit-two"></span>
    <span class="auth-floating-star">✦</span>
  </div>
  @endif
  <div class="auth-artwork-shade"></div>
  <a class="auth-artwork-brand" href="{{ route('home') }}" aria-label="{{ $storeBrand }} ana sayfası">
    <img class="auth-artwork-brand-logo" src="{{ asset($authAppearance->desktopLogo()) }}" alt="{{ $storeBrand }} logosu" width="170" height="43">
  </a>
  <div class="auth-artwork-copy">
    <span class="auth-artwork-kicker">HOŞ GELDİN</span>
    <h2>Keşfetmeye<br>buradan başla.</h2>
    <p>Sevdiğin ürünler, siparişlerin ve sana özel mağaza deneyimi tek bir yerde.</p>
    @if(count($authArtwork) > 1)
      <div class="auth-slide-dots" aria-label="Görseller" data-auth-slide-dots>
        @foreach($authArtwork as $index => $path)
          <button type="button" data-auth-slide-dot="{{ $index }}"
            aria-label="Görsel {{ $index + 1 }}"
            aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
        @endforeach
      </div>
    @endif
  </div>
</aside>