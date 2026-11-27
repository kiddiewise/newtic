@extends('admin.layout')
@section('title','Fatura Entegrasyonları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİSTEM AYARLARI</p><h1>Fatura Entegrasyonları</h1><p>EDM e-Fatura ve e-Arşiv bağlantısı</p></div>
</div>
@if(isset($rateLimitMessage))
  <div class="admin-alert admin-alert-error" role="alert">{{ $rateLimitMessage }} <a href="{{ route('admin.edm.edit') }}">Ayarlar sayfasına dön</a></div>
@endif
<div class="admin-dashboard-grid">
  <section class="admin-panel">
    <div class="admin-editor-heading"><span class="admin-editor-heading-icon"><x-admin-icon name="settings"/></span><div><h2>EDM</h2><p>Test hesabı</p></div></div>
    <p>Bu aşamada bağlantı, kontör, firma, mükellefiyet ve fatura durum sorguları yapılabilir. Canlı fatura gönderimi ve otomatik faturalandırma kapalıdır.</p>
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
    <details><summary>Teknik servis bilgisi</summary><p style="overflow-wrap:anywhere">{{ $endpoint }}</p><p>SOAP 1.1 · Login / CheckCounter / GetInvoiceStatus / Logout</p></details>
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
      @if(($lookup['data']['unverified_records'] ?? 0)>0)<p role="alert">Bazı kayıtlar doğrulanamadı ({{ $lookup['data']['unverified_records'] }}). Otomatik belge türü seçimi yapılmayacaktır.</p>@endif
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
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>Fatura durum sorgulama</h2></div>
  <p>EDM test hesabındaki giden belgeyi ETTN ile sorgulayın. Bu işlem fatura oluşturmaz, göndermez veya sipariş durumunu değiştirmez.</p>
  <form method="POST" action="{{ route('admin.edm.lookup') }}" class="admin-form">
    @csrf <input type="hidden" name="kind" value="invoice_status">
    <label>ETTN<input name="edm_uuid" type="text" maxlength="36" pattern="[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}" autocomplete="off" required></label>
    <label>Belge senaryosu<select name="edm_profile" required><option value="TEMELFATURA">Temel fatura</option><option value="TICARIFATURA">Ticari fatura</option><option value="EARSIVFATURA">e-Arşiv fatura</option></select></label>
    <button type="submit" class="admin-secondary" @disabled($status !== 'ok')>Fatura durumunu sorgula</button>
  </form>
  @if($lookupKind === 'invoice_status')
    @if(($lookup['status'] ?? null) === 'ok')
      @php($result = $lookup['data'])
      <div role="status">
        <p>EDM test ortamında eşleşen kayıt bulundu. Bu sonuç tek başına mali belge onayı değildir.</p>
        <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>ETTN</span><strong>{{ $result['uuid'] }}</strong></div>
        <div class="admin-detail-line"><span>Gözlemlenen fatura numarası</span><strong>{{ $result['invoice_number'] ?? 'Doğrulanmadı' }}</strong></div>
        <div class="admin-detail-line"><span>EDM portal durumu</span><strong>{{ ['packaging'=>'Zarflanıyor','package_failed'=>'Zarflama başarısız','sending'=>'Gönderiliyor','waiting_gib'=>'GİB yanıtı bekleniyor','waiting_system'=>'Sistem yanıtı bekleniyor','send_failed'=>'Gönderim başarısız','send_succeeded'=>'Gönderim işlemi başarılı','waiting_application'=>'Alıcı yanıtı bekleniyor','accepted_succeeded'=>'Alıcı kabul durumu','rejected_succeeded'=>'Alıcı ret durumu','cancelled_succeeded'=>'İptal durumu'][$result['portal_state']] ?? 'Bilinmiyor' }}</strong></div>
        <div class="admin-detail-line"><span>GİB zarf kodu</span><strong>{{ $result['gib']['code'] ?? 'Bildirilmedi' }}</strong></div>
        <p>{{ $result['gib']['message'] }} {{ $result['gib']['action'] }}</p>
        <div class="admin-detail-line"><span>Ticari fatura yanıtı</span><strong>{{ ['accepted'=>'Kabul','rejected'=>'Ret','not_reported'=>'Bildirilmedi','not_applicable'=>'Uygulanmaz','conflict'=>'Çelişkili yanıt — doğrulama gerekli'][$result['commercial_response']] ?? 'Bilinmiyor' }}</strong></div>
        <div class="admin-detail-line"><span>e-Arşiv raporu</span><strong>{{ ['not_reported'=>'Bildirilmedi','uninterpreted'=>'Sonuç doğrulanmadı','not_applicable'=>'Uygulanmaz'][$result['archive_report']] ?? 'Bilinmiyor' }}</strong></div>
        <div class="admin-detail-line"><span>e-Arşiv iptal raporu</span><strong>{{ ['not_reported'=>'Bildirilmedi','uninterpreted'=>'Sonuç doğrulanmadı','not_applicable'=>'Uygulanmaz'][$result['archive_cancel_report']] ?? 'Bilinmiyor' }}</strong></div>
      </div>
    @else
      <p role="alert">Fatura durumu doğrulanamadı. Bu sonuç belgenin bulunmadığını kesinleştirmez.</p>
      @if(isset($lookup['diagnostic']))<p>Teknik sonuç: <code>{{ $lookup['diagnostic'] }}</code></p>@endif
    @endif
    <p>Kontrolsüz tekrar gönderim yapmayın. Sonuçlar kaydedilmez.</p>
  @endif
</section>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>e-Fatura yönlendirme ön kontrolü</h2></div>
  <p>Firma ve mükellef sorgularından seçtiğiniz test etiketleri ile aktif e-Fatura serisini kontrol edin. Numara ayrılmaz, taslak yüklenmez.</p>
  <form method="POST" action="{{ route('admin.edm.lookup') }}" class="admin-form">
    @csrf <input type="hidden" name="kind" value="routing_preview">
    <label>Gönderici VKN/TCKN<input name="seller_id" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <label>Gönderici etiketi (GB)<input name="sender_alias" maxlength="259" autocomplete="off" placeholder="urn:mail:..." required></label>
    <label>Alıcı VKN/TCKN<input name="buyer_id" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <label>Alıcı etiketi (PK)<input name="receiver_alias" maxlength="259" autocomplete="off" placeholder="urn:mail:..." required></label>
    <label>Aktif e-Fatura serisi<input name="series" maxlength="3" pattern="[A-Z0-9]{3}" autocomplete="off" required></label>
    <label>Seri yılı<input name="year" type="number" min="2000" max="2099" value="{{ now()->timezone('Europe/Istanbul')->year }}" required></label>
    <button type="submit" class="admin-secondary" @disabled($status !== 'ok')>Yönlendirmeyi doğrula</button>
  </form>
  @if($lookupKind === 'routing_preview')
    @if(($lookup['status'] ?? null) === 'ok')
      @php($preview = $lookup['data'])
      <div role="status">
        @foreach(['company'=>'Gönderici firma','sender_alias'=>'GB eşleşmesi','series'=>'Aktif e-Fatura serisi','series_integrity'=>'Seri kayıt bütünlüğü','recipient'=>'Alıcı sorgusu','recipient_alias'=>'PK eşleşmesi','recipient_integrity'=>'Alıcı kayıt bütünlüğü'] as $key=>$label)
          <div class="admin-detail-line"><span>{{ $label }}</span><strong>{{ $preview['checks'][$key] === true ? 'Doğrulandı' : ($preview['checks'][$key] === false ? 'Doğrulanamadı' : 'Bilinmiyor') }}</strong></div>
        @endforeach
        <p>Doğrulanamayan seri: {{ $preview['unreadable_series'] ?? 'Bilinmiyor' }} · Doğrulanamayan alıcı kaydı: {{ $preview['unverified_records'] ?? 'Bilinmiyor' }}</p>
        @php($reasonLabels=['series_code'=>'Seri kodu biçimi','series_year'=>'Seri yılı biçimi','series_active_flag'=>'Aktiflik alanı','series_archive_flag'=>'e-Arşiv alanı','series_internet_flag'=>'İnternet satış alanı','recipient_identifier_missing'=>'Alıcı kimliği eksik','recipient_identifier_mismatch'=>'Alıcı kimliği eşleşmedi','recipient_unit'=>'Birim türü','recipient_alias'=>'Etiket biçimi'])
        @foreach(array_merge($preview['series_warning_reasons']??[],$preview['recipient_warning_reasons']??[]) as $reason=>$count)
          @if(isset($reasonLabels[$reason]))<div class="admin-detail-line"><span>{{ $reasonLabels[$reason] }}</span><strong>{{ $count }} alan</strong></div>@endif
        @endforeach
        <p>Bir kayıtta birden fazla alan sorunu olabilir. Alan sayıları kayıt toplamından farklı olabilir.</p>
        <p>{{ $preview['all_checks_passed'] ? 'Yönlendirme ön kontrolleri tamamlandı.' : 'Yönlendirmede çözülmesi gereken kontroller var.' }} Gönderim yetkisi verilmedi.</p>
      </div>
    @else <p role="alert">Yönlendirme ön kontrolü tamamlanamadı.</p> @endif
    <p>Sonuçlar saklanmaz. Bu ekran belge yükleme veya gönderme yetkisini doğrulamaz.</p>
  @endif
</section>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>EDM XML belge doğrulama</h2></div>
  <p>Mevcut giden test belgesini ETTN, senaryo ve taraf kimlikleri ile karşılaştırır. Ham XML gösterilmez veya saklanmaz.</p>
  <form method="POST" action="{{ route('admin.edm.lookup') }}" class="admin-form">
    @csrf <input type="hidden" name="kind" value="invoice_evidence">
    <label>ETTN<input name="edm_uuid" maxlength="36" autocomplete="off" required></label>
    <label>Belge senaryosu<select name="edm_profile" required><option value="TEMELFATURA">Temel fatura</option><option value="TICARIFATURA">Ticari fatura</option><option value="EARSIVFATURA">e-Arşiv fatura</option></select></label>
    <label>Gönderici VKN/TCKN<input name="seller_id" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <label>Alıcı VKN/TCKN<input name="buyer_id" inputmode="numeric" maxlength="11" pattern="[0-9]{10,11}" autocomplete="off" required></label>
    <button type="submit" class="admin-secondary" @disabled($status !== 'ok')>XML kimliğini doğrula</button>
  </form>
  @if($lookupKind === 'invoice_evidence')
    @if(($lookup['status'] ?? null) === 'ok')
      @php($evidence = $lookup['data'])
      <div role="status">
        <p>Belge kimliği eşleşti. Mali geçerlilik veya imza doğrulaması değildir.</p>
        <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>ETTN</span><strong>{{ $evidence['uuid'] }}</strong></div>
        <div class="admin-detail-line"><span>Fatura numarası</span><strong>{{ $evidence['invoice_number'] ?? 'Henüz tahsis edilmedi' }}</strong></div>
        <div class="admin-detail-line"><span>XML boyutu</span><strong>{{ $evidence['xml_bytes'] }} bayt</strong></div>
        <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>SHA-256</span><code>{{ $evidence['xml_sha256'] }}</code></div>
        <div class="admin-detail-line"><span>İmza elemanı</span><strong>{{ $evidence['signature_element_present'] ? 'Mevcut — doğrulanmadı' : 'Bulunmadı' }}</strong></div>
      </div>
    @else <p role="alert">XML belge kimliği doğrulanamadı. Bu sonuç belgenin bulunmadığını kesinleştirmez.</p>@endif
  @endif
</section>
@if(!empty($sandboxTrials) && count($sandboxTrials))
<section class="admin-panel">
  <div class="admin-panel-title"><h2>EDM kontrollü test kayıtları</h2></div>
  <p>Yalnızca test hesabı. Taslak onayı, imza veya GİB gönderim başarısı değildir. Tekrar yükleme yapılamaz.</p>
  @foreach($sandboxTrials as $trial)
    <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>ETTN</span><strong>{{ $trial->uuid }}</strong></div>
    <div class="admin-detail-line"><span>Yükleme</span><strong>{{ ['reserved_unknown'=>'Sonuç belirsiz','unconfirmed'=>'Doğrulanamadı','draft_acknowledged'=>'Taslak onaylandı'][$trial->load_status] ?? 'Bilinmiyor' }}</strong></div>
    <div class="admin-detail-line"><span>Fatura numarası</span><strong>{{ $trial->allocated_invoice_id ?? 'Doğrulanmadı' }}</strong></div>
    @if($trial->diagnostic)<div class="admin-detail-line"><span>Teknik sonuç</span><strong>{{ $trial->diagnostic }}</strong></div>@endif
    @foreach(['status_query'=>'Durum','xml_query'=>'XML','pdf_query'=>'PDF'] as $key=>$label)
      <div class="admin-detail-line"><span>{{ $label }}</span><strong>{{ $trial->$key === 'matched' ? 'Eşleşti' : ($trial->$key ? 'Doğrulanamadı' : 'Henüz alınmadı') }}</strong></div>
    @endforeach
    @foreach($sandboxDocuments[$trial->uuid] ?? [] as $file)
      <a class="admin-secondary" href="{{ route('admin.edm.sandbox.download',['uuid'=>$trial->uuid,'format'=>$file->format]) }}">{{ strtoupper($file->format) }} indir ({{ $file->byte_count }} bayt)</a>
    @endforeach
    <hr>
  @endforeach
</section>
@endif
@endsection
