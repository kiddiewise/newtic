@extends('admin.layout')
@section('title', 'PAYTR Ödeme Ayarları | NEWTIC')
@section('topbar', 'PAYTR Ödeme Ayarları')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">MAĞAZA AYARLARI / ÖDEME ALTYAPISI</p>
    <h1>PAYTR entegrasyonu</h1>
    <p>PAYTR iFrame API için mağaza bilgilerini güvenli şekilde tanımlayın.</p>
  </div>
  <span class="paytr-setup-status">
    <x-admin-icon name="settings" /> İlk kurulum
  </span>
</div>

<section class="admin-panel paytr-setup-banner" aria-label="PAYTR ödeme kurulumu durumu">
  <div class="paytr-setup-heading">
    <div class="paytr-setup-icon"><x-admin-icon name="landmark" /></div>
    <div>
      <h2>{{ $configured ? 'Mağaza bilgileri kayıtlı' : 'PAYTR bağlantı bilgileri bekleniyor' }}</h2>
      <p>{{ $configured
          ? 'Kimlik bilgileri kaydedildi. Sağlayıcı onayı ve aktivasyon tamamlanmadan kartla ödeme açılmaz.'
          : 'İlk adımda Mağaza No, Mağaza Parola ve Mağaza Gizli Anahtar bilgilerini kaydedin.' }}</p>
    </div>
    <span class="paytr-setup-pill">{{ $testEnabled ? ($mode === 'live' ? 'Canlı ödeme açık' : 'Bağlantı doğrulaması açık') : ($configured ? 'Kayıtlı · Aktivasyon bekleniyor' : 'Kurulum gerekli') }}</span>
  </div>
</section>

<section class="admin-panel paytr-test-control" aria-label="PAYTR ödeme aktivasyonu">
  <div>
    <span class="paytr-step">02 / ÖDEME AKTİVASYONU</span>
    <h2>Ödeme ve taksit ayarları</h2>
    <p>Test modu tahsilat yapmaz. PayTR mağazanız canlı onayı aldığında canlı modu seçebilirsiniz.</p>
  </div>
  @if($testAllowed && $configured)
    <form class="admin-form" method="POST" action="{{ route('admin.paytr-settings.test-mode') }}">
      @csrf
      <label>Ödeme durumu
        <select name="enabled"><option value="1" @selected($testEnabled)>Açık</option><option value="0" @selected(!$testEnabled)>Kapalı</option></select>
      </label>
      <label>İşlem modu
        <select name="mode"><option value="test" @selected($mode === 'test')>Test (tahsilat yok)</option><option value="live" @selected($mode === 'live') @disabled(!$liveAllowed)>Canlı (gerçek tahsilat)</option></select>
      </label>
      @unless($liveAllowed)<small>Canlı mod için mağazanın canlı kurulumda olması ve zorunlu yasal bilgilerin tamamlanması gerekir.</small>@endunless
      <label>Taksit seçenekleri
        <select name="installments_enabled"><option value="0" @selected(!$installments)>Kapalı / tek çekim</option><option value="1" @selected($installments)>PayTR izin veriyorsa açık</option></select>
      </label>
      <label>En fazla taksit
        <select name="max_installment">
          <option value="0" @selected($maxInstallment === 0)>PayTR ve mevzuat sınırı</option>
          @foreach(range(2, 12) as $count)<option value="{{ $count }}" @selected($maxInstallment === $count)>{{ $count }} taksit</option>@endforeach
        </select>
      </label>
      <label>Canlı para iadesi
        <select name="live_refunds_enabled"><option value="0" @selected(!$liveRefundsEnabled)>Kapalı</option><option value="1" @selected($liveRefundsEnabled) @disabled(!$liveAllowed)>Açık (gerçek iade)</option></select>
      </label>
      <small>PayTR test iadesi ve durum sorgusu doğrulandıktan sonra açın. Gerçek iade işlemleri geri alınamaz.</small>
      <label><input type="checkbox" name="live_confirmed" value="1"> PayTR mağazam canlı onaylı, bildirim URL'si kayıtlı; gerçek tahsilatı onaylıyorum.</label>
      <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Ödeme ayarlarını kaydet</button>
    </form>
    <form method="POST" action="{{ route('admin.paytr-settings.test-mode') }}">
      @csrf
      <input type="hidden" name="enabled" value="{{ $testEnabled ? 0 : 1 }}">
      <input type="hidden" name="mode" value="test">
      @if($mode === 'test')
        <button class="admin-secondary" type="submit">{{ $testEnabled ? 'Bağlantı doğrulamasını kapat' : 'Bağlantı doğrulamasını aç' }}</button>
      @endif
    </form>
  @else
    <div class="paytr-test-disabled">Ödeme yöntemi henüz etkin değil. HTTPS mağaza adresini ve bilgileri kontrol edin.</div>
  @endif
  <div class="paytr-callback-box">
    <strong>PAYTR Bildirim URL</strong>
    <code>{{ $callbackUrl }}</code>
    <small>PAYTR Mağaza Paneli → Destek &amp; Kurulum → Ayarlar bölümüne bu HTTPS bildirim adresini tanımlayın. Bu adres doğrulanmadan ödeme aktivasyonu tamamlanmış sayılmaz.</small>
  </div>
</section>

<section class="admin-panel paytr-credentials-panel" aria-labelledby="paytr-credentials-title">
  <div class="paytr-section-heading">
    <div>
      <span class="paytr-step">01 / BAĞLANTI BİLGİLERİ</span>
      <h2 id="paytr-credentials-title">PAYTR mağaza bilgileri</h2>
      <p class="admin-description">Bu değerleri PAYTR Mağaza Paneli'ndeki entegrasyon bilgilerinizden alabilirsiniz.</p>
    </div>
    <x-admin-icon name="settings" />
  </div>
  <form method="POST" action="{{ route('admin.paytr-settings.update') }}" class="admin-form paytr-settings-form" autocomplete="off">
    @csrf
    @method('PATCH')
    <label class="paytr-setting-field" for="paytr-merchant-id">
      <span>Mağaza No <code>merchant_id</code></span>
      <input type="text" id="paytr-merchant-id" name="merchant_id" required
        maxlength="64" inputmode="text" autocapitalize="off" autocomplete="off"
        spellcheck="false" placeholder="PAYTR mağaza numaranız"
        value="{{ old('merchant_id', $merchantId) }}">
      <small>Mağaza numarası kayıtlı olduğunda burada görüntülenebilir.</small>
    </label>

    <label class="paytr-setting-field" for="paytr-merchant-key">
      <span>Mağaza Parola <code>merchant_key</code></span>
      <input type="password" id="paytr-merchant-key" name="merchant_key"
        maxlength="2048" autocomplete="new-password" autocorrect="off"
        autocapitalize="off" spellcheck="false"
        placeholder="{{ $hasKey ? 'Kaydedilmiş · Değiştirmek için yeni parola yazın' : 'Mağaza parolasını girin' }}"
        @if(!$hasKey) required @endif>
      <small><x-admin-icon name="check" /> {{ $hasKey ? 'Kayıtlı ve şifreli · Boş bırakırsanız mevcut parola korunur.' : 'Henüz kayıtlı değil · Şifreli saklanır.' }}</small>
    </label>

    <label class="paytr-setting-field" for="paytr-merchant-salt">
      <span>Mağaza Gizli Anahtar <code>merchant_salt</code></span>
      <input type="password" id="paytr-merchant-salt" name="merchant_salt"
        maxlength="2048" autocomplete="new-password" autocorrect="off"
        autocapitalize="off" spellcheck="false"
        placeholder="{{ $hasSalt ? 'Kaydedilmiş · Değiştirmek için yeni anahtar yazın' : 'Gizli anahtarı girin' }}"
        @if(!$hasSalt) required @endif>
      <small><x-admin-icon name="check" /> {{ $hasSalt ? 'Kayıtlı ve şifreli · Boş bırakırsanız mevcut anahtar korunur.' : 'Henüz kayıtlı değil · Şifreli saklanır.' }}</small>
    </label>

    <div class="admin-form-actions paytr-form-actions">
      <span><x-admin-icon name="settings" /> Şifreli kayıt · Yalnızca yöneticiler</span>
      <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Bilgileri güvenle kaydet</button>
    </div>
  </form>
</section>


@endsection
