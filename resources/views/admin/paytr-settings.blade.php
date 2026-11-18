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
          ? 'Kimlik bilgileri kayıtlı. Test ödeme seçeneği açılarak yalnızca sandbox işlemleri denenebilir.'
          : 'İlk adımda Mağaza No, Mağaza Parola ve Mağaza Gizli Anahtar bilgilerini kaydedin.' }}</p>
    </div>
    <span class="paytr-setup-pill">{{ $testEnabled ? 'Test modu açık' : ($configured ? 'Kayıtlı · Doğrulanmadı' : 'Kurulum gerekli') }}</span>
  </div>
</section>

<section class="admin-panel paytr-test-control" aria-label="PAYTR test ödemesini etkinleştir">
  <div>
    <span class="paytr-step">02 / TEST ÖDEME DURUMU</span>
    <h2>PAYTR test ödemesi</h2>
    <p>{{ $testEnabled ? 'Kartla ödeme test mağazasında kullanılabilir. Müşteri yalnızca PAYTR test formuna yönlendirilir.' : 'Test ödemesini açmak için kayıtlı bilgilerinizle sandbox modunu etkinleştirin.' }}</p>
  </div>
  @if($testAllowed && $configured)
    <form method="POST" action="{{ route('admin.paytr-settings.test-mode') }}">
      @csrf
      <input type="hidden" name="enabled" value="{{ $testEnabled ? 0 : 1 }}">
      <button class="{{ $testEnabled ? 'admin-secondary' : 'admin-primary' }}" type="submit">
        <x-admin-icon name="{{ $testEnabled ? 'close' : 'check' }}" />
        {{ $testEnabled ? 'Test ödemesini kapat' : 'Test ödemesini etkinleştir' }}
      </button>
    </form>
  @else
    <div class="paytr-test-disabled">Önce mağaza bilgilerini kaydedin. Test modu yalnızca yetkili staging alan adında açılabilir.</div>
  @endif
  <div class="paytr-callback-box">
    <strong>PAYTR Bildirim URL</strong>
    <code>{{ route('checkout.paytr.callback') }}</code>
    <small>PAYTR Mağaza Paneli → Destek &amp; Kurulum → Ayarlar bölümüne bu HTTPS bildirim adresini tanımlayın. Bu olmadan test ödemeniz tamamlanmış sayılmaz.</small>
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
