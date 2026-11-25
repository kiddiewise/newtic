@php
  $mobileOrderSnapshot = \App\Support\AdminOrderNotifications::snapshot(auth()->user());
  $mobileOrderCount = $mobileOrderSnapshot['count'];
  $mobileLinks = [
    ['title'=>'Siparişler','icon'=>'bag','route'=>'admin.orders.index','active'=>'admin.orders.*'],
    ['title'=>'Ürünler','icon'=>'package','route'=>'admin.products.index','active'=>'admin.products.*'],
    ['title'=>'Analizler','icon'=>'chart','route'=>'admin.analytics','active'=>'admin.analytics*'],
  ];
  // Reuse the sidebar groups so mobile and desktop never drift apart.
  $mobileDirectRoutes = collect($mobileLinks)->pluck('route')->all();
  $mobileMoreGroups = collect($menuGroups)->map(fn($group)=>[
    'label'=>$group['label'],
    'items'=>collect($group['items'])->reject(fn($item)=>in_array($item['name'],$mobileDirectRoutes,true))->values(),
  ]);
  $mobileMoreActive = request()->routeIs('admin.dashboard')
    || $mobileMoreGroups->contains(fn($group)=>$group['items']->contains(fn($item)=>request()->routeIs($item['active'])))
    || request()->routeIs('admin.sections.*','admin.slides.*');
  $mobileActiveIndex = -1;
  foreach ($mobileLinks as $index => $item) {
    if (request()->routeIs($item['active'])) { $mobileActiveIndex = $index; break; }
  }
  if ($mobileMoreActive) $mobileActiveIndex = 3;
@endphp
<nav class="admin-mobile-nav" aria-label="Mobil yönetim" data-admin-mobile-nav data-active-index="{{ $mobileActiveIndex }}" style="--admin-nav-index:{{ max(0,$mobileActiveIndex) }}" data-order-latest-id="{{ $mobileOrderSnapshot['latest_id'] }}" data-order-user="{{ auth()->id() }}" data-order-badge-url="{{ route('admin.orders.badge', [], false) }}">
  <span class="admin-mobile-nav-slider" data-admin-nav-slider aria-hidden="true" @if($mobileActiveIndex < 0) hidden @endif></span>
  @foreach($mobileLinks as $item)
    @php($active = request()->routeIs($item['active']))
    <a href="{{ route($item['route']) }}" @class(['admin-mobile-nav-item','is-active'=>$active]) data-admin-nav-index="{{ $loop->index }}"
      @if($active) aria-current="page" @endif
      @if($loop->first) data-admin-order-link aria-label="Siparişler{{ $mobileOrderCount ? ', '.$mobileOrderCount.' yeni sipariş' : '' }}" @endif>
      <span class="admin-mobile-nav-icon"><x-admin-icon :name="$item['icon']" />
        @if($loop->first)
          <span class="admin-mobile-order-badge" data-admin-order-badge aria-hidden="true" @if(!$mobileOrderCount) hidden @endif>{{ $mobileOrderCount > 99 ? '99+' : $mobileOrderCount }}</span>
        @endif
      </span>
      <span>{{ $item['title'] }}</span>
    </a>
  @endforeach
  <button type="button" @class(['admin-mobile-nav-item','is-active'=>$mobileMoreActive]) data-admin-more-open data-admin-nav-index="3" aria-expanded="false" aria-haspopup="dialog" aria-controls="admin-mobile-more">
    <span class="admin-mobile-nav-icon"><x-admin-icon name="more" /></span>
    <span>Daha Fazla</span>
  </button>
  <span class="admin-mobile-sr-only" aria-live="polite" data-admin-order-live></span>
</nav>
<dialog class="admin-mobile-more" id="admin-mobile-more" data-admin-more-dialog aria-labelledby="admin-mobile-more-title">
  <div class="admin-mobile-more-inner">
    <div class="admin-mobile-more-handle" aria-hidden="true"></div>
    <header class="admin-mobile-more-header">
      <div><h2 id="admin-mobile-more-title">Daha Fazla</h2></div>
      <button type="button" class="admin-mobile-more-close" data-admin-more-close aria-label="Menüyü kapat"><x-admin-icon name="close" /></button>
    </header>
    <div class="admin-mobile-more-grid">
      <a href="{{ route('admin.dashboard') }}" @class(['admin-mobile-more-link','is-active'=>request()->routeIs('admin.dashboard')]) @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>
        <span class="admin-mobile-more-icon"><x-admin-icon name="home"/></span><span>Genel Bakış</span>
      </a>
      <a href="{{ route('home') }}" target="_blank" rel="noopener noreferrer" class="admin-mobile-more-link"><span class="admin-mobile-more-icon"><x-admin-icon name="external-link"/></span><span>Mağazayı görüntüle</span></a>
      @foreach($mobileMoreGroups as $group)
        @php($groupActive = $group['items']->contains(fn($item)=>request()->routeIs($item['active']) || ($item['name']==='admin.index' && request()->routeIs('admin.sections.*','admin.slides.*'))))
        <details class="newtic-mobile-more-group" @if($groupActive || $group['label']==='Ürün & Sipariş Yönetimi') open @endif>
          <summary>{{ $group['label'] }}<x-admin-icon name="arrow-right"/></summary>
          @foreach($group['items'] as $item)
            @php($active = request()->routeIs($item['active']) || ($item['name']==='admin.index' && request()->routeIs('admin.sections.*','admin.slides.*')))
            <a href="{{ route($item['name']).($item['fragment'] ?? '') }}" @class(['admin-mobile-more-link','is-active'=>$active]) @if($active) aria-current="page" @endif>
              <span class="admin-mobile-more-icon"><x-admin-icon :name="$item['icon']"/></span><span>{{ $item['label'] }}</span>
              @if(($item['count'] ?? 0)>0)<strong class="admin-side-count">{{ $item['count'] }}</strong>@endif
            </a>
          @endforeach
        </details>
      @endforeach
    </div>
  </div>
</dialog>
