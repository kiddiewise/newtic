{{-- For nested category pages only. Visible when scrolling down past the original mobile subnav. --}}
<nav class="sticky-sibling-nav" data-sticky-sibling-nav
  aria-label="{{ $siblingParent->name }} alt kategorileri" aria-hidden="true" inert>
  <div class="mobile-category-pills sticky-sibling-pills" data-sticky-sibling-track
    tabindex="0" aria-label="{{ $siblingParent->name }} alt kategorileri, yatay kaydırılabilir">
    <a class="mobile-pill sticky-sibling-all" href="{{ route('category.show', $siblingParent->slug) }}"
      aria-label="{{ $siblingParent->name }} kategorisindeki tüm ürünler">Tümü</a>
    @foreach($siblingCategories as $sibling)
      <a href="{{ route('category.show', $sibling->slug) }}"
        class="mobile-pill sticky-sibling-pill {{ $sibling->id === $category->id ? 'is-active' : '' }}"
        @if($sibling->id === $category->id) aria-current="page" data-sticky-sibling-current @endif>
        {{ $sibling->name }}
      </a>
    @endforeach
  </div>
</nav>
