@extends('admin.layout')
@section('title', 'Ürün Rozetleri | NEWTIC')
@section('topbar', 'Ürün Rozetleri')
@section('content')
<div class="admin-page-heading">
  <div>
    <p class="admin-eyebrow">KATALOG / ROZET YÖNETİMİ</p>
    <h1>Ürün rozetleri</h1>
    <p>Pastel şerit rozetleri oluştur, yazısını ve rengini yönet. Hangi üründe görüneceklerini ürün düzenleme ekranından seç.</p>
  </div>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert-error" role="alert">
    <strong>Rozet kaydedilemedi.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
  </div>
@endif

<section class="admin-panel">
  <div class="admin-panel-title"><h2>Yeni rozet oluştur</h2></div>
  <form method="POST" action="{{ route('admin.product-badges.store') }}" class="admin-form badge-admin-create">
    @csrf
    <label>Rozet yazısı
      <input type="text" name="name" maxlength="50" value="{{ old('name') }}" placeholder="Örn. Ücretsiz Kargo" required>
    </label>
    <label>Pastel renk
      <select name="color" required>
        @foreach($colors as $key => $label)
          <option value="{{ $key }}" @selected(old('color', 'mint') === $key)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Sıralama
      <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 100) }}" required>
    </label>
    <label class="admin-check badge-admin-active">
      <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Yayında
    </label>
    <div class="badge-admin-actions">
      <button type="submit" class="admin-primary">+ Rozet ekle</button>
    </div>
  </form>
  <div class="badge-palette-guide" aria-label="Kullanılabilir pastel renkler">
    @foreach($colors as $key => $label)
      <span class="product-badge product-badge--{{ $key }}">{{ $label }}</span>
    @endforeach
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>Tanımlı rozetler</h2><span>{{ $badges->count() }} rozet</span></div>
  <div class="badge-admin-list">
    @forelse($badges as $badge)
      <div class="badge-admin-item">
        <div class="badge-admin-item-heading">
          <span class="product-badge product-badge--{{ $badge->color }}">{{ $badge->name }}</span>
          <span class="badge-admin-count">{{ $badge->products_count }} üründe · {{ $badge->is_active ? 'Aktif' : 'Gizli' }}</span>
        </div>
        <form class="admin-form badge-admin-edit" action="{{ route('admin.product-badges.update', $badge) }}" method="POST">
          @csrf @method('PATCH')
          <label>Rozet yazısı
            <input name="name" value="{{ $badge->name }}" maxlength="50" required>
          </label>
          <label>Renk
            <select name="color" required>
              @foreach($colors as $key => $label)
                <option value="{{ $key }}" @selected($badge->color === $key)>{{ $label }}</option>
              @endforeach
            </select>
          </label>
          <label>Sıra
            <input type="number" name="sort_order" min="0" max="9999" value="{{ $badge->sort_order }}" required>
          </label>
          <label class="admin-check badge-admin-active">
            <input type="checkbox" name="is_active" value="1" @checked($badge->is_active)> Yayında
          </label>
          <div class="badge-admin-actions">
            <button class="admin-primary" type="submit">Kaydet</button>
          </div>
        </form>
        <form method="POST" action="{{ route('admin.product-badges.destroy', $badge) }}" onsubmit="return confirm('Bu rozet tüm ürünlerden de kaldırılacak. Silinsin mi?')">
          @csrf @method('DELETE')
          <button type="submit" class="admin-danger">Rozeti sil</button>
        </form>
      </div>
    @empty
      <p class="admin-empty">Henüz ürün rozeti tanımlanmadı.</p>
    @endforelse
  </div>
</section>
@endsection
