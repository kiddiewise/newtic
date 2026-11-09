@extends('admin.layout')
@section('title', 'Kayan Duyurular | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <p class="admin-eyebrow">MAĞAZA BİLDİRİMLERİ</p><h1>Kayan duyurular</h1>
  <p>Birden fazla duyuru ekleyip başlangıç/bitiş tarihi ve saatini belirleyebilirsiniz. Tüm saatler Türkiye saatidir (İstanbul).</p>
</div>
<div class="admin-panel">
  <h2>Yeni duyurular ekle</h2>
  <form method="POST" action="{{ route('admin.announcements.store') }}" class="admin-form admin-settings-grid">
    @csrf
    <label class="admin-full">Duyuru cümleleri
      <textarea name="messages" rows="5" maxlength="5000" required placeholder="₺1000 üzeri siparişlerde kargo bedava&#10;Polaris Surf Kurşun koleksiyonunu keşfedin">{{ old('messages') }}</textarea>
      <small>Her satır ayrı duyuru olur. En fazla 12 satır, satır başına 240 karakter. Duyurular üst bantta sırayla kayar.</small>
    </label>
    <label>Başlangıç — Türkiye saati
      <input type="datetime-local" name="starts_at" value="{{ old('starts_at') }}">
      <small>Boşsa hemen gösterilir.</small>
    </label>
    <label>Bitiş — Türkiye saati
      <input type="datetime-local" name="ends_at" value="{{ old('ends_at') }}">
      <small>Boşsa süresizdir.</small>
    </label>
    <label>Gösterim sırası
      <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}" required>
    </label>
    <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', true))> Yayınlansın</label>
    <div class="admin-form-actions"><button class="admin-primary" type="submit">Duyuruları ekle</button></div>
  </form>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Yönetilen duyurular</h2><span>{{ $announcements->count() }} duyuru</span></div>
  @forelse($announcements as $announcement)
    <div class="admin-announcement-item">
      <form method="POST" action="{{ route('admin.announcements.update', $announcement) }}" class="admin-form admin-settings-grid">
        @csrf @method('PATCH')
        <label class="admin-full">Mesaj
          <input type="text" name="message" maxlength="240" required value="{{ $announcement->message }}">
        </label>
        <label>Başlangıç — Türkiye saati
          <input type="datetime-local" name="starts_at" value="{{ $announcement->starts_at?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i') }}">
        </label>
        <label>Bitiş — Türkiye saati
          <input type="datetime-local" name="ends_at" value="{{ $announcement->ends_at?->timezone('Europe/Istanbul')->format('Y-m-d\TH:i') }}">
        </label>
        <label>Sıra <input type="number" name="sort_order" min="0" max="9999" required value="{{ $announcement->sort_order }}"></label>
        <label class="admin-check"><input type="checkbox" name="is_active" value="1" @checked($announcement->is_active)> Aktif</label>
        <div class="admin-form-actions"><button type="submit" class="admin-primary">Kaydet</button></div>
      </form>
      <form method="POST" action="{{ route('admin.announcements.destroy', $announcement) }}" onsubmit="return confirm('Bu duyuruyu silmek istiyor musunuz?')">
        @csrf @method('DELETE')<button type="submit" class="admin-danger">Duyuruyu sil</button>
      </form>
      <p class="admin-description">Durum: {{ !$announcement->is_active ? 'Kapalı' : ($announcement->starts_at && $announcement->starts_at->isFuture() ? 'Zamanlanmış' : ($announcement->ends_at && $announcement->ends_at->isPast() ? 'Süresi doldu' : 'Aktif')) }}</p>
    </div>
  @empty
    <p class="admin-empty">Henüz duyuru eklenmedi. Duyuru ekleyene kadar mağazanın mevcut üst bandı korunur.</p>
  @endforelse
</div>
@endsection
