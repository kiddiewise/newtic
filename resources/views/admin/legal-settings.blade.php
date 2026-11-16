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
<section class="admin-panel" id="footer-isaretleri">
  <div class="admin-panel-title"><h2>Ödeme, güven ve uygulama görselleri</h2></div>
  <p class="admin-description">Visa, Mastercard, TROY, PAYTR, TR GO veya meslek derneği logolarını yalnızca kullanım yetkin varsa ekle. Gerçek bir sertifika, üyelik veya aktif ödeme hizmeti olmadan görsel gösterme. Yeni mağazalarda hiçbiri varsayılan olarak yüklenmez.</p>
  <form method="POST" action="{{ route('admin.legal.marks.store') }}" enctype="multipart/form-data" class="admin-form admin-settings-grid">
    @csrf
    <label>Görsel türü
      <select name="type" required>
        @foreach($markTypes as $type => $label)
          <option value="{{ $type }}" @selected(old('type') === $type)>{{ $label }}</option>
        @endforeach
      </select>
    </label>
    <label>Görsel açıklaması
      <input name="title" required maxlength="100" placeholder="Örn. izinli ödeme kuruluşu logosu" value="{{ old('title') }}">
    </label>
    <label>Görsel seç (PNG/JPG/WebP, 2 MB)
      <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
    </label>
    <label>Doğrulama veya resmi mağaza bağlantısı (HTTPS)
      <input type="url" name="verification_url" maxlength="500" placeholder="https://" value="{{ old('verification_url') }}">
      <small>Güven damgaları ve mobil uygulamalar için zorunlu. Ödeme markasında varsa resmi link ekleyin.</small>
    </label>
    <label>Gösterim sırası
      <input type="number" name="sort_order" min="0" max="9999" value="{{ old('sort_order', 0) }}">
    </label>
    <label class="admin-check admin-full">
      <input type="checkbox" name="authorization_confirmed" value="1" required>
      Görseli yayınlamak için geçerli kullanım hakkımın, gerçek üyeliğimin veya aktif ödeme hizmeti yetkimin bulunduğunu doğruluyorum.
    </label>
    <div class="admin-form-actions"><button type="submit" class="admin-primary">İzinli görseli ekle</button></div>
  </form>
  @if($marks->isNotEmpty())
    <div class="legal-admin-marks">
      @foreach($marks as $mark)
        <div class="legal-admin-mark">
          <img src="{{ asset($mark->image_path) }}" alt="{{ $mark->title }}" width="90" height="46" loading="lazy">
          <div>
            <strong>{{ $mark->title }}</strong>
            <small>{{ $markTypes[$mark->type] ?? $mark->type }}</small>
          </div>
          <form method="POST" action="{{ route('admin.legal.marks.destroy', $mark) }}">
            @csrf @method('DELETE')
            <button class="admin-secondary" type="submit">Kaldır</button>
          </form>
        </div>
      @endforeach
    </div>
  @endif
</section>
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
