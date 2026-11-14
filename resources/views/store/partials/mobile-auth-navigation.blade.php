@php
  $authParams = array_filter(['next' => $authNext, 'favorite' => $authNext === 'cart' ? $favoriteId : '', 'review_product' => $authNext === 'review' ? $reviewProduct : '']);
  // The child auth view renders before the parent layout initializes its
  // own variables, so resolve the current logo here independently.
  $authAppearance = \App\Models\ShopAppearance::current();
@endphp
<header class="mobile-auth-header" aria-label="Üyelik ekranı üst menüsü">
  <a href="{{ route('home') }}" class="mobile-auth-brand" aria-label="{{ \App\Support\StoreIdentity::name() }} ana sayfa">
    @if($authAppearance->mobile_logo_path)
      <img src="{{ asset($authAppearance->mobile_logo_path) }}" alt="{{ \App\Support\StoreIdentity::name() }}" width="156" height="48">
    @elseif($authAppearance->desktop_logo_path)
      <img src="{{ asset($authAppearance->desktop_logo_path) }}" alt="{{ \App\Support\StoreIdentity::name() }}" width="156" height="48">
    @else
      <span class="mobile-auth-fallback">
        <x-store-icon name="shopping-bag" />
        <span><strong>{{ mb_strtoupper(\App\Support\StoreIdentity::wordmark()[0], 'UTF-8') }}</strong>@if(\App\Support\StoreIdentity::wordmark()[1])<small>{{ mb_strtoupper(\App\Support\StoreIdentity::wordmark()[1], 'UTF-8') }}</small>@endif</span>
      </span>
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
