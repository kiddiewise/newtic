@php
  $authParams = array_filter(['next' => $authNext, 'favorite' => $authNext === 'cart' ? $favoriteId : '']);
@endphp
<header class="mobile-auth-header" aria-label="Üyelik ekranı üst menüsü">
  <a href="{{ route('home') }}" class="mobile-auth-brand" aria-label="Polaris Kurşun ana sayfa">
    @if($shopAppearance->mobile_logo_path)
      <img src="{{ asset($shopAppearance->mobile_logo_path) }}" alt="Polaris Kurşun" width="156" height="48">
    @elseif($shopAppearance->desktop_logo_path)
      <img src="{{ asset($shopAppearance->desktop_logo_path) }}" alt="Polaris Kurşun" width="156" height="48">
    @else
      <strong>POLARIS <span>KURŞUN</span></strong>
    @endif
  </a>
  <a href="{{ route('home') }}" class="mobile-auth-close" aria-label="Üyelik ekranından çık"><x-store-icon name="x" /></a>
</header>
<nav class="mobile-auth-tabs" aria-label="Giriş ve üyelik seçimi">
  <a href="{{ route('account.login', $authParams) }}" @class(['is-active' => request()->routeIs('account.login')])
    @if(request()->routeIs('account.login')) aria-current="page" @endif>Giriş Yap</a>
  <a href="{{ route('account.register', $authParams) }}" @class(['is-active' => request()->routeIs('account.register')])
    @if(request()->routeIs('account.register')) aria-current="page" @endif>Üye Ol</a>
</nav>
