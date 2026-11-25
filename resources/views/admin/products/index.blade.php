@extends('admin.layout')
@section('title', 'Ürünler | NEWTIC')
@section('content')
<div class="newtic-products">
  <div class="newtic-products-heading">
    <div><p class="admin-eyebrow">KATALOG</p><h1>Ürün yönetimi</h1><p>{{ number_format($products->total(), 0, ',', '.') }} ürün</p></div>
    <div class="newtic-products-heading-actions">
      <details class="newtic-order-actions newtic-product-import-actions">
        <summary>İşlemler <x-admin-icon name="more" /></summary>
        <div>
          <a href="{{ route('admin.products.bulk.export') }}"><x-admin-icon name="download" /> Excel'e aktar (CSV)</a>
          <a href="{{ route('admin.products.bulk.index') }}"><x-admin-icon name="layers" /> Toplu ürün yükle</a>
          <a href="{{ route('admin.product-imports.index') }}"><x-admin-icon name="layers" /> WooCommerce'den aktar</a>
        </div>
      </details>
      <a class="newtic-product-add" href="{{ route('admin.products.create') }}"><x-admin-icon name="plus" /> Yeni ürün</a>
    </div>
  </div>
  <div class="newtic-product-panel">
    <form class="newtic-product-filter" method="GET" action="{{ route('admin.products.index') }}" role="search">
      <input type="hidden" name="status" value="{{ $status }}">
      <div class="newtic-product-search"><x-admin-icon name="search" />
        <input type="search" name="q" value="{{ $term }}" maxlength="100" placeholder="Ürün adı veya SKU ile ara" aria-label="Ürün adı veya SKU ile ara">
        <button type="submit">Ara</button>
      </div>
      <div class="newtic-product-filter-fields">
        <label>Kategori<select name="category">
          <option value="">Tüm kategoriler</option>
          @foreach($categories as $choice)
            <option value="{{ $choice['category']->id }}" @selected($categoryId===$choice['category']->id)>{{ $choice['label'] }}</option>
          @endforeach
        </select></label>
        <label>Sıralama<select name="sort">
          @foreach(['newest'=>'En yeni','oldest'=>'En eski','name'=>'Ürün adı (A–Z)','price_asc'=>'En düşük fiyat','price_desc'=>'En yüksek fiyat','stock_asc'=>'En az stok','views_desc'=>'En çok görüntülenen'] as $key=>$label)
            <option value="{{ $key }}" @selected($sort===$key)>{{ $label }}</option>
          @endforeach
        </select></label>
      </div>
      <div class="newtic-product-filter-actions">
        <a href="{{ route('admin.products.index') }}">Temizle</a><button type="submit">Filtrele</button>
      </div>
    </form>
    <nav class="newtic-product-tabs" aria-label="Ürün filtreleri">
      @foreach(['all'=>'Tümü','active'=>'Yayında','inactive'=>'Pasif','low_stock'=>'Düşük stok'] as $key=>$label)
        <a href="{{ route('admin.products.index', array_filter(['status'=>$key,'q'=>$term,'category'=>$categoryId,'sort'=>$sort])) }}" @if($status===$key) aria-current="page" @endif>{{ $label }}</a>
      @endforeach
    </nav>
    <div class="admin-catalog-list newtic-product-list">
      @forelse($products as $product)
        <article class="admin-catalog-row newtic-product-row" data-quick-edit-row="{{ $product->id }}">
          <a class="newtic-product-photo-link" href="{{ route('admin.products.edit',$product) }}" aria-label="{{ $product->name }} düzenle">
            <img class="admin-catalog-photo" src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="" loading="lazy" width="76" height="76">
          </a>
          <div class="admin-catalog-info newtic-product-info">
            <a class="newtic-product-name" href="{{ route('admin.products.edit',$product) }}" data-quick-row-name>{{ $product->name }}</a>
            <small>SKU: {{ $product->sku }}</small>
            <small data-quick-row-category>{{ $product->category?->name ?: 'Kategori yok' }}</small>
            <div class="newtic-product-price">
              <strong data-quick-row-price>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
              <s data-quick-row-regular @if($product->discount_percent === 0) hidden @endif>{{ $product->compare_at_price ? number_format((float) $product->compare_at_price, 2, ',', '.') : '' }} ₺</s>
              <span class="admin-sale-tag" data-quick-row-discount @if($product->discount_percent === 0) hidden @endif>-%{{ $product->discount_percent }}</span>
            </div>
          </div>
          <div class="newtic-product-state">
            @if($product->tracks_stock)
              <span data-quick-row-stock @class(['admin-stock-badge','is-low' => $product->stock_quantity <= 10])>Stok: {{ $product->stock_quantity }}</span>
            @else
              <span class="admin-stock-badge" data-quick-row-stock>Stok takibi kapalı</span>
            @endif
            <span class="admin-status {{ $product->is_active ? 'is-active' : '' }}">{{ $product->is_active ? 'Yayında' : 'Pasif' }}</span>
            <small class="newtic-product-views"><x-admin-icon name="chart" /> {{ number_format($product->view_count, 0, ',', '.') }} görüntülenme</small>
          </div>
          <div class="newtic-product-row-actions">
          <button type="button" class="admin-quick-edit-trigger" data-quick-edit-open
            data-product-id="{{ $product->id }}"
            data-product-url="{{ route('admin.products.quick-update', $product) }}"
            data-edit-url="{{ route('admin.products.edit', $product) }}"
            data-name="{{ $product->name }}"
            data-category-id="{{ $product->primary_category_id }}"
            data-regular-price="{{ $product->compare_at_price ?: $product->price }}"
            data-sale-price="{{ $product->compare_at_price ? $product->price : '' }}"
            data-stock="{{ $product->stock_quantity }}"
            data-tracks-stock="{{ $product->tracks_stock ? '1' : '0' }}"
            aria-haspopup="dialog">Hızlı düzenle</button>
            <a class="newtic-product-edit" href="{{ route('admin.products.edit',$product) }}"><x-admin-icon name="settings" /> Düzenle</a>
            <details class="newtic-product-overflow">
              <summary aria-label="{{ $product->name }} için diğer işlemler"><x-admin-icon name="more" /></summary>
              <div>
          <form method="POST" action="{{ route('admin.products.destroy', $product) }}" onsubmit="return confirm('Bu ürünü kalıcı olarak silmek istiyor musunuz? Sipariş geçmişi bulunan ürünler silinemez.')">
            @csrf @method('DELETE')
            <button class="admin-danger" type="submit">Sil</button>
          </form>
              </div>
            </details>
          </div>
        </article>
      @empty
        <div class="admin-empty"><x-admin-icon name="package" /><strong>Bu filtrede ürün bulunamadı.</strong><p>Aramayı veya filtreleri değiştir, ya da yeni ürün ekle.</p></div>
      @endforelse
    </div>
    <div class="pagination-wrap">{{ $products->links() }}</div>
  </div>
</div>
<dialog class="admin-quick-edit-dialog" data-quick-editor aria-labelledby="quick-editor-title" aria-describedby="quick-editor-description">
  <div class="admin-quick-edit-sheet">
    <div class="admin-quick-edit-handle" aria-hidden="true"></div>
    <div class="admin-quick-edit-heading">
      <div>
        <p class="admin-eyebrow">ÜRÜN YÖNETİMİ</p>
        <h2 id="quick-editor-title">Hızlı düzenle</h2>
        <p id="quick-editor-description">Değişiklikler otomatik kaydedilir.</p>
      </div>
      <button class="admin-quick-edit-close" type="button" data-quick-close aria-label="Hızlı düzenlemeyi kapat">×</button>
    </div>
    <form class="admin-quick-edit-form" data-quick-form autocomplete="off" novalidate>
      <label class="admin-quick-edit-wide" for="quick-edit-name">Ürün adı
        <input id="quick-edit-name" name="name" data-quick-field maxlength="190" required type="text">
      </label>
      <label class="admin-quick-edit-wide" for="quick-edit-category">Kategori
        <select id="quick-edit-category" name="primary_category_id" data-quick-field required>
          <option value="">Kategori seçin</option>
          @foreach($categories as $choice)
            <option value="{{ $choice['category']->id }}">{{ $choice['label'] }}{{ !$choice['category']->is_active ? ' (pasif)' : '' }}</option>
          @endforeach
        </select>
      </label>
      <label for="quick-edit-regular">Normal fiyat (₺)
        <input id="quick-edit-regular" name="regular_price" data-quick-field type="text" inputmode="decimal" required placeholder="Örn. 150,00">
      </label>
      <label for="quick-edit-sale">İndirimli fiyat (₺)
        <input id="quick-edit-sale" name="sale_price" data-quick-field type="text" inputmode="decimal" placeholder="İndirim yok">
      </label>
      <label class="admin-quick-edit-wide" for="quick-edit-stock">Stok adedi
        <input id="quick-edit-stock" name="stock_quantity" data-quick-field type="number" inputmode="numeric" required min="0" max="100000000" step="1">
      </label>
      <p class="admin-quick-edit-hint admin-quick-edit-wide" data-quick-stock-hint hidden>Bu üründe stok takibi kapalı. Girdiğiniz adet kaydedilir ancak stok takibi açılmadan satışta kullanılmaz.</p>
      <p class="admin-quick-edit-notice admin-quick-edit-wide" data-quick-feedback role="status" aria-live="polite">Bir alanı değiştirmeniz yeterli; kaydet butonu yok.</p>
      <div class="admin-quick-edit-footer admin-quick-edit-wide">
        <a data-quick-full-edit class="admin-secondary" href="#">Ayrıntılı düzenle</a>
        <button type="button" class="admin-quick-edit-done" data-quick-close>Tamam</button>
      </div>
    </form>
  </div>
</dialog>
<p class="admin-description">Görüntülenme sayısı 24 saatlik oturum filtrelemesiyle hesaplanır; kesin tekil kişi sayısı değildir. Her gramaj ayrı ürün olarak yönetilir.</p>
@endsection
