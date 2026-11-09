@foreach($items as $menuCategory)
  @if($menuCategory->childrenRecursive->isNotEmpty())
    <details class="mobile-menu-group" data-category-depth="{{ $level }}">
      <summary>
        <span>{{ $menuCategory->name }}</span>
        <span class="mobile-menu-chevron" aria-hidden="true">⌄</span>
      </summary>
      <div class="mobile-menu-children">
        <a class="mobile-menu-all" href="{{ route('category.show', $menuCategory->slug) }}">Tüm {{ $menuCategory->name }} ürünleri <span aria-hidden="true">›</span></a>
        @include('store.partials.category-menu-items', ['items' => $menuCategory->childrenRecursive, 'level' => $level + 1])
      </div>
    </details>
  @else
    <a class="mobile-menu-leaf" href="{{ route('category.show', $menuCategory->slug) }}">{{ $menuCategory->name }} <span aria-hidden="true">›</span></a>
  @endif
@endforeach
