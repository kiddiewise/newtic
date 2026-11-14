@extends('admin.layout')
@section('title', '301 URL Yönlendirmeleri | NEWTIC')
@section('topbar', 'SEO / 301 Yönlendirmeler')
@section('content')
<div class="admin-page-heading">
  <div>
    <p class="admin-eyebrow">SEO / ALAN ADI VE SİTE TAŞIMA</p>
    <h1>301 URL Yönlendirmeleri</h1>
    <p>Eski ürün, kategori veya içerik adreslerini yeni bağlantılarına taşı. WordPress, WooCommerce ve diğer sistemlerden geçişte kullanılabilir.</p>
  </div>
</div>
<section class="admin-panel" style="margin-bottom:16px">
  <p class="admin-description"><strong>Nasıl çalışır?</strong> Eski URL ile yeni URL'yi gir. Sistem yalnızca yolu kaydeder ve eski adrese gelen ziyaretçiyi <strong>301 kalıcı yönlendirme</strong> ile yeni adrese gönderir. Alan adı değiştiğinde hedefin alan adı otomatik güncellenir.</p>
  <p class="admin-description">Eski bir alan adından tamamen farklı bir domaine geçiliyorsa, eski domainin de bu uygulamaya yönlendirilmesi veya eski sunucuda HTTPS 301 yönlendirmesi yapılması gerekir. Uygulama kendisine hiç ulaşmayan istekleri yönlendiremez.</p>
</section>
@if($errors->any())
<div class="admin-alert admin-alert-error" role="alert"><strong>Yönlendirme kaydedilemedi</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
<section class="admin-panel" style="margin-bottom:16px">
  <div class="admin-panel-title"><h2>Yeni 301 yönlendirmesi</h2></div>
  <form method="POST" action="{{ route('admin.redirects.store') }}" class="admin-form" style="display:grid;gap:12px;max-width:800px">
    @csrf
    <label>Eski URL / yol
      <input name="old_path" value="{{ old('old_path') }}" maxlength="2048" placeholder="/eski-urun-adi veya https://eski-site.com/eski-urun-adi" required>
    </label>
    <label>Yeni URL / yol
      <input name="new_path" value="{{ old('new_path') }}" maxlength="2048" placeholder="/urun/yeni-urun-adi" required>
    </label>
    <label><input type="checkbox" name="is_active" value="1" checked> Yönlendirme etkin</label>
    <button class="admin-primary" type="submit">301 yönlendirmesini kaydet</button>
  </form>
</section>
<section class="admin-panel" style="margin-bottom:16px">
  <div class="admin-panel-title"><h2>CSV dosyasıyla toplu aktarım</h2></div>
  <p class="admin-description">Excel'de iki sütun oluştur: <code>old_path;new_path</code>. Her satır bir yönlendirmedir. Noktalı virgül ayracı ve UTF-8 kodlaması kullan; tek seferde en fazla 500 satır. Hatalı dosyada hiçbir yönlendirme değiştirilmez.</p>
  <form method="POST" action="{{ route('admin.redirects.import') }}" enctype="multipart/form-data" class="admin-form" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
    @csrf
    <input type="file" name="csv" accept=".csv,.txt,text/csv" required>
    <button class="admin-secondary" type="submit">CSV yönlendirmelerini içe aktar</button>
  </form>
</section>
<section class="admin-panel">
  <div class="admin-panel-title"><h2>Kayıtlı yönlendirmeler ({{ $count }})</h2></div>
  <p class="admin-description">Etkin: {{ $active }}. İstatistikler yönlendirmeye gelen isteklere göredir.</p>
  <form method="GET" action="{{ route('admin.redirects.index') }}" class="admin-form" style="display:flex;gap:10px;margin-bottom:16px;flex-wrap:wrap">
    <input name="q" placeholder="URL ara" value="{{ $term }}" maxlength="120">
    <button class="admin-secondary" type="submit">Ara</button>
  </form>
  @forelse($entries as $redirect)
    <div style="border-top:1px solid #dde7ed;padding:13px 0;display:grid;gap:9px">
      <div style="display:flex;flex-wrap:wrap;gap:9px;align-items:center">
        <strong style="overflow-wrap:anywhere">{{ $redirect->old_path }}</strong>
        <span aria-hidden="true">→</span>
        <span style="overflow-wrap:anywhere">{{ $redirect->new_path }}</span>
        <small>{{ $redirect->is_active ? 'Aktif' : 'Pasif' }} · {{ $redirect->hit_count }} kullanım</small>
      </div>
      <form method="POST" action="{{ route('admin.redirects.update', $redirect) }}" class="admin-form" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">
        @csrf @method('PATCH')
        <input name="new_path" aria-label="Yeni hedef URL" maxlength="2048" value="{{ $redirect->new_path }}" style="flex:1;min-width:180px" required>
        <label><input type="checkbox" name="is_active" value="1" @checked($redirect->is_active)> Etkin</label>
        <input type="hidden" name="is_active" value="0">
        <button type="submit" class="admin-secondary">Güncelle</button>
      </form>
      <form method="POST" action="{{ route('admin.redirects.destroy', $redirect) }}" onsubmit="return confirm('Bu yönlendirme silinsin mi?')">
        @csrf @method('DELETE')
        <button type="submit" class="admin-secondary">Sil</button>
      </form>
    </div>
  @empty
    <p class="admin-description">Henüz 301 yönlendirmesi eklenmedi.</p>
  @endforelse
  {{ $entries->links() }}
</section>
@endsection