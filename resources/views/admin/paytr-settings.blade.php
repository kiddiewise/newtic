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
          ? 'Bilgiler şifrelenmiş olarak saklandı. PAYTR bağlantısı ve ödeme doğrulaması henüz test edilmedi.'
          : 'İlk adımda Mağaza No, Mağaza Parola ve Mağaza Gizli Anahtar bilgilerini kaydedin.' }}</p>
    </div>
    <span class="paytr-setup-pill">{{ $configured ? 'Kayıtlı · Doğrulanmadı' : 'Kurulum gerekli' }}</span>
  </div>
  <div class="paytr-setup-notice">
    <x-admin-icon name="info" />
    <span><strong>Güvenlik:</strong> Kartla ödeme henüz aktif değil. Bu ekran yalnızca API bilgilerinin saklanmasını sağlar. Mevcut Havale/EFT ödeme akışı değişmez.</span>
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

    <div class="paytr-secrets-warning">
      <x-admin-icon name="info" />
      <span>Parola ve gizli anahtar <strong>kaydedildikten sonra ekranda görüntülenmez.</strong>
        Yeni Mağaza No girerseniz iki gizli bilgiyi de tekrar doldurun.
        Anahtarları sohbet mesajı, GitHub veya ekran görüntüsü ile paylaşmayın.</span>
    </div>
    <div class="admin-form-actions paytr-form-actions">
      <span><x-admin-icon name="settings" /> Şifreli kayıt · Yalnızca yöneticiler</span>
      <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Bilgileri güvenle kaydet</button>
    </div>
  </form>
</section>

<section class="admin-panel paytr-setup-next" aria-labelledby="paytr-next-title">
  <div class="paytr-section-heading">
    <div>
      <span class="paytr-step">02 / SIRADAKİ ADIMLAR</span>
      <h2 id="paytr-next-title">Ödeme bağlantısı için kontrol listesi</h2>
    </div>
  </div>
  <div class="paytr-checklist">
    <div><span class="paytr-check-icon {{ $configured ? 'is-done' : '' }}"><x-admin-icon name="{{ $configured ? 'check' : 'clock' }}" /></span>
      <span><strong>Mağaza bilgileri</strong><small>{{ $configured ? 'Kaydedildi; gerçek PAYTR bağlantısı henüz doğrulanmadı.' : 'Önce üç mağaza bilgisini kaydedin.' }}</small></span></div>
    <div><span class="paytr-check-icon"><x-admin-icon name="clock" /></span>
      <span><strong>1. Adım — iFrame token</strong><small>Laravel üzerinden güvenli POST, tutar ve sepet doğrulaması.</small></span></div>
    <div><span class="paytr-check-icon"><x-admin-icon name="clock" /></span>
      <span><strong>2. Adım — Ödeme bildirim URL'si</strong><small>PAYTR'nin gönderdiği imzalı POST sonucunun tek sefer işlenmesi ve yalnızca OK yanıtı.</small></span></div>
    <div><span class="paytr-check-icon"><x-admin-icon name="clock" /></span>
      <span><strong>Test ve canlıya geçiş</strong><small>Başarılı / başarısız test, stok ve tekrar eden bildirim kontrolleri tamamlanmadan kartlı ödeme açılmaz.</small></span></div>
  </div>
  <p class="admin-description">Bu sayfa PAYTR'ye API isteği göndermez ve anahtarların doğru olduğunu henüz doğrulamaz.</p>
  <div class="paytr-official-links">
    <a href="https://dev.paytr.com/iframe-api/iframe-api-1-adim" target="_blank" rel="noopener noreferrer">PAYTR iFrame API 1. Adım <x-admin-icon name="external-link" /></a>
    <a href="https://dev.paytr.com/iframe-api/iframe-api-2-adim" target="_blank" rel="noopener noreferrer">PAYTR iFrame API 2. Adım <x-admin-icon name="external-link" /></a>
  </div>
</section>
@endsection
