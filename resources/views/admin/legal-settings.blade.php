@extends('admin.layout')
@section('title', 'Yasal Sayfalar ve Satıcı Bilgileri | NEWTIC')
@section('topbar', 'Yasal Sayfalar')
@section('content')
<div class="admin-page-heading">
  <div>
    <p class="admin-eyebrow">MAĞAZA / HUKUKİ BİLGİLER</p>
    <h1>Yasal sayfalar ve kurumsal bilgiler</h1>
    <p>Her mağazanın satıcı kimliği ve metinleri ayrıdır. Taslaklar ziyaretçilere gösterilmez.</p>
  </div>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Yayına çıkış kontrolü</h2></div>
  @if(count($missing))
    <p class="admin-description">PAYTR başvurusu ve canlı satış hazırlığı için aşağıdaki alanları tamamla:</p>
    <ul class="legal-admin-checklist">@foreach($missing as $label)<li>{{ $label }}</li>@endforeach</ul>
  @else
    <p class="admin-description">Temel satıcı kimliği ve gerekli sayfalar yayımlanmış görünüyor. Bu kontrol metinlerin hukuken yeterli olduğunu veya PAYTR onayını garanti etmez.</p>
  @endif
  <p class="admin-description">Yasal metinlerin içeriği işletmenin kendi verilerine, ürünlerine, veri işleme faaliyetlerine ve güncel mevzuata göre avukat veya hukuk danışmanı tarafından incelenmelidir. Buraya başka bir sitenin sözleşmesini kopyalama.</p>
</div>
<div class="admin-panel" id="satici-bilgileri">
  <div class="admin-panel-title"><h2>Satıcı kimliği ve iletişim</h2></div>
  <form method="POST" action="{{ route('admin.legal.profile') }}" class="admin-form admin-settings-grid">
    @csrf @method('PATCH')
    <label>Ticari unvan / satıcı adı *<input name="legal_name" maxlength="220" value="{{ old('legal_name', $profile->legal_name) }}" placeholder="Tescilli ticari unvan"></label>
    <label>İletişim e-postası *<input name="contact_email" type="email" maxlength="255" value="{{ old('contact_email', $profile->contact_email) }}" placeholder="destek@magaza.com"></label>
    <label>Telefon *<input name="contact_phone" maxlength="35" value="{{ old('contact_phone', $profile->contact_phone) }}" placeholder="+90 ..."></label>
    <label>KEP adresi<input name="kep_address" type="email" maxlength="255" value="{{ old('kep_address', $profile->kep_address) }}" placeholder="ornek@hs01.kep.tr"></label>
    <label>MERSİS numarası (varsa / uygunsa)<input name="mersis_number" maxlength="40" value="{{ old('mersis_number', $profile->mersis_number) }}"></label>
    <label>Vergi dairesi<input name="tax_office" maxlength="130" value="{{ old('tax_office', $profile->tax_office) }}"></label>
    <label>Vergi kimlik numarası<input name="tax_number" maxlength="30" value="{{ old('tax_number', $profile->tax_number) }}"></label>
    <label>Destek çalışma saatleri<input name="support_hours" maxlength="160" value="{{ old('support_hours', $profile->support_hours) }}" placeholder="Hafta içi 09.00–18.00"></label>
    <label class="admin-full">Şirket / işyeri açık adresi *<textarea name="registered_address" maxlength="2000" rows="3">{{ old('registered_address', $profile->registered_address) }}</textarea></label>
    <label class="admin-full admin-check">
      <input type="hidden" name="show_paytr_disclosure" value="0">
      <input type="checkbox" name="show_paytr_disclosure" value="1" @checked(old('show_paytr_disclosure', $profile->show_paytr_disclosure))>
      PAYTR işyeri sözleşmeme ve aktif hesabıma uygun ödeme hizmeti bilgilendirmesini göster
      <small>Yalnızca gerçekten PAYTR kullanıyorsan etkinleştir. Bu işaret kart tahsilatını açmaz.</small>
    </label>
    <div class="admin-form-actions"><button type="submit" class="admin-primary">Satıcı bilgilerini kaydet</button></div>
  </form>
</div>
<div class="legal-admin-document-list">
  @foreach($pageLabels as $slug => $label)
    @php $document = $pages[$slug] ?? null; @endphp
    <section class="admin-panel" id="{{ $slug }}">
      <div class="admin-panel-title">
        <h2>{{ $label }}</h2>
        <span>{{ $document?->is_published ? 'Yayında · Sürüm '.$document->version : 'Taslak / eksik' }}</span>
      </div>
      <form method="POST" action="{{ route('admin.legal.page', $slug) }}" class="admin-form admin-settings-grid">
        @csrf @method('PATCH')
        <label class="admin-full">Sayfa başlığı
          <input name="title" maxlength="180" required value="{{ old('title', $document?->title ?? $label) }}">
        </label>
        <label class="admin-full">Bu mağazaya özel içerik
          <textarea name="body" rows="9" maxlength="50000" placeholder="Satıcıya özel, hukuken incelenmiş metni buraya yazın. HTML kodu kullanılmaz.">{{ old('body', $document?->body) }}</textarea>
          <small>Metin düz yazı olarak saklanır, HTML çalıştırılmaz. Yayın için en az 200 karakter gerekir. Güncel sözleşmeyi değiştirince sürüm numarası yükselir.</small>
        </label>
        <div class="admin-full legal-admin-document-bottom">
          <label class="admin-check">
            <input type="hidden" name="is_published" value="0">
            <input type="checkbox" name="is_published" value="1" @checked(old('is_published', $document?->is_published))>
            Bu sayfayı yayınla (hukuki içerik kontrolünü yaptım)
          </label>
          @if($document?->is_published)
            <a href="{{ route('legal.show', $slug) }}" class="admin-secondary" target="_blank" rel="noopener noreferrer">Yayındaki sayfayı görüntüle</a>
          @endif
          <button type="submit" class="admin-primary">Kaydet</button>
        </div>
      </form>
    </section>
  @endforeach
</div>
@endsection
