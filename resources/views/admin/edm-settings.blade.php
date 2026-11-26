@extends('admin.layout')
@section('title','Fatura Entegrasyonları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİSTEM AYARLARI</p><h1>Fatura Entegrasyonları</h1><p>EDM e-Fatura ve e-Arşiv bağlantısı</p></div>
</div>
<div class="admin-dashboard-grid">
  <section class="admin-panel">
    <div class="admin-editor-heading"><span class="admin-editor-heading-icon"><x-admin-icon name="settings"/></span><div><h2>EDM</h2><p>Test hesabı</p></div></div>
    <p>Bu aşamada bağlantı, kontör, firma ve mükellefiyet sorguları yapılabilir. Canlı fatura gönderimi ve otomatik faturalandırma kapalıdır.</p>
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
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>Firma ve fatura serileri</h2></div>
  <p>EDM test hesabınıza bağlı firmanın VKN veya TCKN bilgisini girin. Yalnızca sorgulama yapılır.</p>
  <form method="POST" action="{{ route('admin.edm.lookup') }}" class="admin-form">
    @csrf <input type="hidden" name="kind" value="company">
    <label>Firma vergi / kimlik numarası<input name="edm_identifier" type="text" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <button type="submit" class="admin-secondary" @disabled($status !== 'ok')>Firma ve serileri sorgula</button>
  </form>
  @if($lookupKind === 'company')
    @if($lookup['status'] === 'ok')
      <div role="status" class="admin-detail-line"><span>Firma</span><strong>{{ $lookup['data']['title'] }}</strong></div>
      <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>Gönderici birim (GB)</span><strong>{{ $lookup['data']['gb'] ?: 'Tanımlı değil' }}</strong></div>
      <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>Posta kutusu (PK)</span><strong>{{ $lookup['data']['pk'] ?: 'Tanımlı değil' }}</strong></div>
      <h3>Belge serileri</h3>
      @forelse($lookup['data']['series'] as $series)
        <div class="admin-detail-line"><span>{{ $series['code'] }} · {{ $series['year'] }}<small> · {{ $series['archive'] ? 'e-Arşiv' : 'e-Fatura' }}{{ $series['internet'] ? ' / İnternet satışı' : '' }}</small></span><strong>{{ $series['active'] ? 'Aktif' : 'Pasif' }}</strong></div>
      @empty <p>Seri kaydı dönmedi. EDM panelindeki seri tanımlarını kontrol edin.</p> @endforelse
      @if(($lookup['data']['unreadable_series'] ?? 0)>0)<p role="alert">{{ $lookup['data']['unreadable_series'] }} seri kaydı doğrulanamadı. Bunlar kullanılmayacaktır.</p>@endif
      <p>Seriler yalnızca listelenir; numara tahsisi yapılmaz.</p>
    @else
      <p role="alert">Firma sorgusu doğrulanamadı. Bu sonuç firmanın bulunmadığını kesinleştirmez.</p>
      @if(isset($lookup['diagnostic']))<p>Teknik sonuç: <code>{{ $lookup['diagnostic'] }}</code></p>@endif
    @endif
  @endif
</section>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>e-Fatura mükellef sorgulama</h2></div>
  <p>Test ortamında alıcının posta kutusu etiketlerini sorgulayın. Sonuçlar kaydedilmez.</p>
  <form method="POST" action="{{ route('admin.edm.lookup') }}" class="admin-form">
    @csrf <input type="hidden" name="kind" value="taxpayer">
    <label>Alıcı vergi / kimlik numarası<input name="edm_identifier" type="text" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <button type="submit" class="admin-secondary" @disabled($status !== 'ok')>Mükellefi sorgula</button>
  </form>
  @if($lookupKind === 'taxpayer')
    @if($lookup['status'] === 'ok')
      @if(count($lookup['data']['aliases']))
        <p role="status">e-Fatura posta kutusu bulundu (test).</p>
        @foreach($lookup['data']['aliases'] as $alias)<div class="admin-detail-line" style="overflow-wrap:anywhere">{{ $alias }}</div>@endforeach
      @elseif(count($lookup['data']['sender_aliases'] ?? []))
        <p role="status">Gönderici birim bulundu; aktif alıcı posta kutusu bulunamadı (test). Bu sonuç e-Arşiv kararı değildir.</p>
        @foreach($lookup['data']['sender_aliases'] as $alias)<div class="admin-detail-line" style="overflow-wrap:anywhere">{{ $alias }}</div>@endforeach
      @else
        <p role="status">Aktif posta kutusu bulunamadı (test). Bu sonuç tek başına e-Arşiv fatura kesme yetkisi vermez.</p>
      @endif
    @else
      <p role="alert">Mükellef sorgusu doğrulanamadı. Belge türü belirlenmedi.</p>
      @if(isset($lookup['diagnostic']))<p>Teknik sonuç: <code>{{ $lookup['diagnostic'] }}</code></p>@endif
    @endif
  @endif
</section>
@endsection
