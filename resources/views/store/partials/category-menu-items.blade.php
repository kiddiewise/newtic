@foreach($items as $menuCategory)
  @if($menuCategory->childrenRecursive->isNotEmpty())
    <details class="mobile-menu-group mega-tree-group" data-category-depth="{{ $level }}"
      @if(in_array($menuCategory->slug, $activeCategorySlugs ?? [], true)) open @endif>
      <summary class="mega-tree-summary">
        <span class="mega-category-visual">
          <img src="{{ asset($menuCategory->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" loading="lazy" width="56" height="56">
        </span>
        <span class="mega-category-copy">
          <strong>{{ $menuCategory->name }}</strong>
          <small>{{ $menuCategory->childrenRecursive->count() }} alt kategori</small>
        </span>
        <span class="mega-chevron-icon" aria-hidden="true">
          <svg viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></svg>
        </span>
      </summary>
      <div class="mobile-menu-children mega-tree-children">
        <a class="mobile-menu-all mega-tree-all" href="{{ route('category.show', $menuCategory->slug) }}">
          Tüm {{ $menuCategory->name }} ürünleri
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
        </a>
        @include('store.partials.category-menu-items', ['items' => $menuCategory->childrenRecursive, 'level' => $level + 1])
      </div>
    </details>
  @else
    <a class="mobile-menu-leaf mega-tree-leaf" data-category-depth="{{ $level }}" href="{{ route('category.show', $menuCategory->slug) }}">
      <span class="mega-category-visual">
        <img src="{{ asset($menuCategory->image_path ?: 'assets/categories/renkli-kursun.svg') }}" alt="" loading="lazy" width="56" height="56">
      </span>
      <span class="mega-category-copy"><strong>{{ $menuCategory->name }}</strong></span>
      <svg class="mega-leaf-arrow" viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
    </a>
  @endif
@endforeach
