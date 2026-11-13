@extends('admin.layout')
@section('title', 'Google Merchant Center | NEWTIC')
@section('topbar', 'Entegrasyonlar / Google Merchant Center')
@section('content')
<div class="admin-page-heading">
  <div>
    <p class="admin-eyebrow">ENTEGRASYONLAR / GOOGLE</p>
    <h1>Google Merchant Center</h1>
    <p>Ürünlerini Google’a bağla, senkronize et ve stok/fiyat bilgilerini güncel tut.</p>
  </div>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert-error" role="alert">
    <strong>Google entegrasyonunda bir sorun oluştu.</strong>
    <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
  </div>
@endif

<div class="google-merchant-overview">
  <div class="admin-panel">
    <span class="admin-eyebrow">GOOGLE HESABI</span>
    <h2>{{ $hasToken ? 'Bağlandı' : ($setting->isConfigured() ? 'Bağlantı bekleniyor' : 'Kurulum gerekli') }}</h2>
    <p class="admin-description">OAuth 2.0 · Şifreli kimlik bilgileri</p>
  </div>
  <div class="admin-panel">
    <span class="admin-eyebrow">MERCHANT CENTER</span>
    <h2>{{ $setting->merchant_id ?: 'Seçilmedi' }}</h2>
    <p class="admin-description">Veri kaynağı: {{ $setting->data_source_name ? 'Hazır' : 'Oluşturulmadı' }}</p>
  </div>
  <div class="admin-panel">
    <span class="admin-eyebrow">YAYINDAKİ ÜRÜNLER</span>
    <h2>{{ number_format($productCount, 0, ',', '.') }}</h2>
    <p class="admin-description">Google koşullarına uygunluk ayrıca incelenir.</p>
  </div>
</div>

<section class="admin-panel google-merchant-section">
  <div class="admin-panel-title"><h2>1. Google Cloud OAuth bilgileri</h2></div>
  <p class="admin-description">Google Cloud Console’da Web uygulaması OAuth istemcisi oluşturup Merchant API erişimini etkinleştirin. Yalnızca bu mağazanın admin panelinden kurulum yapılabilir.</p>
  <div class="google-merchant-uri">
    <strong>İzin verilen yönlendirme URI (Authorized redirect URI)</strong>
    <code>{{ route('admin.google-merchant.callback') }}</code>
  </div>
  <form class="admin-form google-merchant-form" method="POST" action="{{ route('admin.google-merchant.save') }}" autocomplete="off">
    @csrf @method('PATCH')
    <label>Google OAuth Client ID
      <input type="text" name="client_id" maxlength="255" required value="{{ old('client_id', $setting->client_id) }}" placeholder="...apps.googleusercontent.com" autocomplete="off">
    </label>
    <label>Google OAuth Client Secret
      <input type="password" name="client_secret" maxlength="2048" placeholder="{{ $hasSecret ? 'Kayıtlı, değiştirmek için yeni değer girin' : 'Gizli anahtar' }}" autocomplete="new-password" {{ !$hasSecret ? 'required' : '' }}>
      <small>{{ $hasSecret ? 'Mevcut gizli anahtar şifreli olarak kayıtlıdır. Bu alan boş bırakılırsa korunur.' : 'Şifreli biçimde saklanır; kaydettikten sonra tekrar gösterilmez.' }}</small>
    </label>
    <button type="submit" class="admin-primary">OAuth bilgilerini kaydet</button>
  </form>
</section>

<section class="admin-panel google-merchant-section">
  <div class="admin-panel-title"><h2>2. Google hesabını bağla</h2></div>
  <p class="admin-description">Google’ın kendi ekranında izin vereceksiniz. NEWTIC, Google hesabı parolanızı görmez. OAuth doğrulaması olmayan bir Google Cloud uygulaması için Google uyarısı veya kullanıcı sınırı uygulanabilir.</p>
  <div class="google-merchant-actions">
    <form method="POST" action="{{ route('admin.google-merchant.connect') }}">
      @csrf
      <button type="submit" class="admin-primary" @disabled(!$setting->isConfigured())>Google ile bağlan</button>
    </form>
    @if($hasToken)
      <form method="POST" action="{{ route('admin.google-merchant.disconnect') }}" onsubmit="return confirm('Google bağlantısı kesilecek. Merchant Center içindeki eski ürünler otomatik silinmez. Devam edilsin mi?')">
        @csrf
        <button type="submit" class="admin-secondary">Bağlantıyı kes</button>
      </form>
    @endif
  </div>
</section>

<section class="admin-panel google-merchant-section">
  <div class="admin-panel-title"><h2>3. Merchant Center hesabını seç</h2></div>
  <p class="admin-description">Bağlandığınız Google hesabının Merchant Center üzerinde yetkili kullanıcı olması gerekir. Seçimden sonra yalnızca NEWTIC’e özel API ürün kaynağı oluşturulur.</p>
  <div class="google-merchant-actions">
    <form method="POST" action="{{ route('admin.google-merchant.accounts') }}">@csrf
      <button class="admin-secondary" type="submit" @disabled(!$hasToken)>Merchant hesaplarını listele</button>
    </form>
  </div>
  @if(count($accounts))
  <form class="admin-form google-merchant-form" method="POST" action="{{ route('admin.google-merchant.select') }}">
    @csrf
    <label>Yetkili Merchant Center hesabı
      <select name="account" required>
        @foreach($accounts as $account)
          <option value="{{ $account['name'] }}">{{ $account['label'] }} — {{ $account['name'] }}</option>
        @endforeach
      </select>
    </label>
    <button class="admin-primary" type="submit">Hesabı seç</button>
  </form>
  @endif
  @if($setting->merchant_id)
    <p class="admin-description">Seçili hesap: <strong>{{ $setting->merchant_id }}</strong></p>
    @unless($setting->data_source_name)
      <form method="POST" action="{{ route('admin.google-merchant.prepare') }}">@csrf
        <button class="admin-primary" type="submit">Google API ürün kaynağı oluştur</button>
      </form>
    @else
      <p class="admin-description">API kaynağı: <code>{{ $setting->data_source_name }}</code></p>
    @endunless
  @endif
</section>

<section class="admin-panel google-merchant-section">
  <div class="admin-panel-title"><h2>4. Ürünleri Google'a gönder</h2></div>
  <p class="admin-description">Yayınlanan ve fotoğrafı bulunan ürünler Google’a aktarılır. İndirim, stok durumu, başlık, açıklama ve ürün görselleri gönderilir. Her gramaj Google’da ayrı ürün olarak kalır.</p>
  <div data-google-merchant-sync
    data-start-url="{{ route('admin.google-merchant.start') }}"
    data-step-url="{{ route('admin.google-merchant.step') }}"
    data-csrf="{{ csrf_token() }}"
    data-running="{{ $setting->sync_status === 'running' ? '1' : '0' }}">
    <button type="button" class="admin-primary" data-sync-launch @disabled(!$setting->isReady())>Ürünleri Google ile eşitle</button>
    <button type="button" class="admin-secondary" data-sync-resume @disabled(!$setting->isReady() || $setting->sync_status !== 'running')>Kalan aktarımı sürdür</button>
    <div class="google-merchant-progress" role="status" aria-live="polite" data-sync-progress>
      <strong>Son durum: {{ $setting->sync_status }}</strong>
      <p>Gönderilen {{ $setting->sync_sent }}, değişmeyen {{ $setting->sync_unchanged }}, kaldırılan {{ $setting->sync_removed }}, hatalı {{ $setting->sync_errors }}.</p>
      @if($setting->last_synced_at)<p>Son aktarım: {{ $setting->last_synced_at->format('d.m.Y H:i') }}</p>@endif
    </div>
  </div>
  @if($errors->count())
    <div class="google-merchant-errors">
      <h3>Ürün bazlı son hatalar</h3>
      @foreach($errors as $issue)
        <p>Ürün #{{ $issue->product_id ?: $issue->offer_id }}: {{ $issue->message }}</p>
      @endforeach
    </div>
  @endif
  <div class="google-merchant-autosync">
    <form method="POST" action="{{ route('admin.google-merchant.auto-sync') }}">
      @csrf @method('PATCH')
      <input type="hidden" name="enabled" value="{{ $setting->auto_sync ? '0' : '1' }}">
      <button class="admin-secondary" type="submit" @disabled(!$setting->isReady())>
        {{ $setting->auto_sync ? 'Zamanlanmış eşitlemeyi kapat' : 'Zamanlanmış eşitlemeyi aç' }}
      </button>
    </form>
    <small>Otomatik eşitleme için Hostinger üzerinde Laravel zamanlayıcısı/cron etkin olmalıdır. Ürün fiyat ve stok değişiklikleri API eşitlemesinde gönderilir; Google'da işlenme süresi değişebilir.</small>
  </div>
</section>

<section class="admin-panel google-merchant-section">
  <div class="admin-panel-title"><h2>Alternatif: Otomatik XML katalog bağlantısı</h2></div>
  <p class="admin-description">Google Cloud OAuth kurmadan da Merchant Center’a ürün ekleyebilirsin. Merchant Center → Ayarlar → Veri kaynakları → Ürün kaynağı ekle → Dosyadan → Zamanlanmış getirme alanına bu URL’yi yaz. Google dosyayı belirlediğin günlük programla yeniden alır.</p>
  <div class="google-merchant-uri">
    <strong>Google Merchant Center XML feed bağlantısı</strong>
    <a href="{{ route('google-merchant.feed') }}" target="_blank" rel="noopener noreferrer">{{ route('google-merchant.feed') }}</a>
  </div>
  <p class="admin-description">Bu URL herkese açık yalnızca yayınlanan ürün verilerini içerir. Web sitesi alanının Merchant Center'da doğrulanmış ve sahipliği onaylanmış olması gerekir. Geliştirme alan adını gerçek mağaza alan adı yerine kullanma. Ürünleri aynı anda hem XML hem API birincil kaynaklarından göndermemenizi öneririz.</p>
  <p class="admin-description">Google'ın ürün onayı garanti edilmez; kargo, iade, mağaza doğrulama ve ürün tanımlayıcıları Merchant Center'da tamamlanmalıdır. Merchant API için Google Cloud projesi ve geliştirici kaydı gerekebilir.</p>
  <a class="admin-secondary" href="https://merchants.google.com/" target="_blank" rel="noopener noreferrer">Google Merchant Center'a git</a>
</section>
@endsection