@php
  $visibleBadges = $product->badges->where('is_active', true);
@endphp
@if($visibleBadges->isNotEmpty())
<div class="product-badge-strip {{ ($placement ?? 'card') === 'detail' ? 'product-badge-strip--detail' : '' }}" aria-label="Ürün rozetleri">
  @foreach($visibleBadges as $badge)
    <span class="product-badge product-badge--{{ $badge->color }}">{{ $badge->name }}</span>
  @endforeach
</div>
@endif
