@extends('admin.layout')
@section('title','Fatura Entegrasyonları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİSTEM AYARLARI</p><h1>Fatura Entegrasyonları</h1><p>EDM e-Fatura ve e-Arşiv bağlantısı</p></div>
</div>
<div class="admin-dashboard-grid">
  <section class="admin-panel">
    <div class="admin-editor-heading"><span class="admin-editor-heading-icon"><x-admin-icon name="settings"/></span><div><h2>EDM</h2><p>Test hesabı</p></div></div>
    <p>Bu aşamada yalnızca bağlantı ve kontör sorgusu yapılabilir. Canlı fatura gönderimi ve otomatik faturalandırma kapalıdır.</p>
    <form method="POST" action="{{ route('admin.edm.update') }}" class="admin-form">
      @csrf @method('PATCH')
      <label>Test kullanıcı adı<input name="edm_username" type="text" required maxlength="255" autocomplete="off" placeholder="{{ $configured ? 'Kaydedildi — değiştirmek için yeniden girin' : 'EDM test kullanıcı adı' }}"></label>
      <label>Test şifresi<input name="edm_password" type="password" required maxlength="1024" autocomplete="new-password" placeholder="{{ $configured ? 'Kaydedildi — değiştirmek için yeniden girin' : 'EDM test şifresi' }}"></label>
      <button class="admin-primary" type="submit">Bilgileri kaydet</button>
    </form>
  </section>
  <section class="admin-panel">
    <div class="admin-panel-title"><h2>Bağlantı durumu</h2></div>
    <div class="admin-detail-line"><span>Ortam</span><strong>Test</strong></div>
    <div class="admin-detail-line"><span>Hesap</span><strong>{{ $configured ? 'Kaydedildi' : 'Tanımlanmadı' }}</strong></div>
    <div class="admin-detail-line"><span>Son kontrol</span><strong>{{ $checkedAt?->format('d.m.Y H:i') ?? 'Henüz yapılmadı' }}</strong></div>
    <div class="admin-detail-line"><span>Sonuç</span><strong>{{ ['ok'=>'Bağlantı doğrulandı','failed'=>'Oturum açılamadı','counter_failed'=>'Oturum açıldı, kontör sorgusu başarısız','logout_failed'=>'Kontör alındı, oturum kapatma doğrulanamadı'][$status ?? ''] ?? 'Bekliyor' }}</strong></div>
    <div class="admin-detail-line"><span>Kalan test kontörü</span><strong>{{ $counter !== null ? number_format($counter,0,',','.') : '—' }}</strong></div>
    <form method="POST" action="{{ route('admin.edm.probe') }}" class="admin-form">@csrf<button type="submit" class="admin-secondary" @disabled(!$configured)>Bağlantıyı test et</button></form>
    <details><summary>Teknik servis bilgisi</summary><p style="overflow-wrap:anywhere">{{ $endpoint }}</p><p>SOAP 1.1 · Login / CheckCounter / Logout</p></details>
  </section>
</div>
@endsection
