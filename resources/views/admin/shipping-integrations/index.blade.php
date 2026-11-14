@extends('admin.layout')
@section('title', 'Kargo Entegrasyonu | NEWTIC')
@section('topbar', 'Kargo Entegrasyonu')
@section('content')
<div class="ship-operations-nav"><a class="admin-secondary" href="{{ route('admin.shipping-operations.index') }}"><x-admin-icon name="package"/> Kargo operasyonlarını ve sorunlu gönderileri görüntüle</a></div>
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">MAĞAZA AYARLARI / KARGO ENTEGRASYONU</p>
    <h1>Kargo entegrasyonu</h1>
    <p>Kargo entegratörünü bağla, DHL ve diğer kargo hesaplarını aynı yerden yönet.</p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.orders.index') }}"><x-admin-icon name="bag"/> Siparişler</a>
</div>

<section class="admin-panel ship-setup-hero">
  <div class="ship-setup-head">
    <span class="ship-provider-icon"><x-admin-icon name="truck"/></span>
    <div><h2>{{ $configured ? 'Kargo Entegratör bilgileri kayıtlı' : 'Kargo sağlayıcısı seç ve bağla' }}</h2>
      <p>{{ $enabled ? 'Dummy Kargo test gönderileri etkin. Gerçek kargo gönderileri henüz kapalı.' : 'Henüz gerçek kargo gönderimi yapılmaz. Hesap bağlantısı ve güvenli Dummy Kargo testleri hazırlanıyor.' }}</p></div>
    <span class="ship-setup-status">{{ $enabled ? 'TEST AÇIK' : ($configured ? 'API KAYITLI' : 'KURULUM GEREKLİ') }}</span>
  </div>
  <p class="ship-hint"><strong>Canlı kargo koruması:</strong> DHL dahil gerçek taşıyıcılara gönderi üretimi devre dışı. Kargo Entegratör hesabındaki Dummy Kargo ile gerçek kargo ücreti oluşturmadan test yapabilirsin.</p>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>1. Sağlayıcı ve API bilgileri</h2><span>Şifreli kayıt</span></div>
  <form class="admin-form ship-settings-form" method="POST" action="{{ route('admin.shipping-integrations.save') }}" autocomplete="off">
    @csrf @method('PATCH')
    <label>Entegratör firma
      <select name="provider" required>
        <option value="kargoentegrator" selected>Kargo Entegratör</option>
        <option value="kargonomi" disabled>Kargonomi (yakında)</option>
      </select>
      <small>İleride başka firmalar eklenebilecek; mevcut sipariş kayıtları bağımsız tutulur.</small>
    </label>
    <label>Kargo Entegratör API anahtarı
      <input type="password" name="api_key" minlength="8" maxlength="2048" autocomplete="new-password"
        autocapitalize="off" spellcheck="false" {{ $configured ? '' : 'required' }}
        placeholder="{{ $configured ? 'Kaydedildi · Değiştirmek için yeni anahtar girin' : 'Paneldeki API anahtarını buraya girin' }}">
      <small>{{ $configured ? 'Anahtar şifreli saklanıyor. Boş bırakırsan mevcut anahtar korunur.' : 'Ayarlar → Satış Kanalları / API bölümünden alabilirsin.' }}</small>
    </label>
    <p class="ship-hint">API anahtarını WhatsApp, sohbet, ekran görüntüsü veya GitHub üzerinden paylaşma. Yeni anahtar kaydedildiğinde test gönderileri otomatik kapatılır; bağlantıyı tekrar doğrulaman gerekir.</p>
    <button class="admin-primary" type="submit"><x-admin-icon name="check"/> Bilgileri güvenle kaydet</button>
  </form>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>2. Kargo firmaları ve depolar</h2><span>{{ $lastCheckedAt ? $lastCheckedAt->format('d.m.Y H:i') : 'Henüz senkronize edilmedi' }}</span></div>
  <p class="admin-description">Kargo Entegratör panelinde önce DHL hesabını ve gönderici deponu tanımla. NEWTIC, oradaki kayıtları API üzerinden okur; DHL şifreni NEWTIC'e ayrıca girmen gerekmez.</p>
  @if($configured)
    <form method="POST" action="{{ route('admin.shipping-integrations.sync') }}">
      @csrf
      <button class="admin-secondary" type="submit"><x-admin-icon name="refresh-cw"/> Bağlantıyı kontrol et ve hesapları yenile</button>
    </form>
  @else
    <p class="ship-info">Önce API anahtarını kaydet, ardından firmaları ve depoları buraya aktar.</p>
  @endif
  <div class="ship-grid">
    <div class="ship-list-panel">
      <h3>Kargo firmaları <small>{{ $carriers->count() }}</small></h3>
      @forelse($carriers as $carrier)
        <div class="ship-account-row"><span><strong>{{ $carrier->name }}</strong>
          <small>Entegrasyon ID: {{ $carrier->remote_id }} · {{ $carrier->carrier_code ?: 'Taşıyıcı türü doğrulanamadı' }}</small></span>
          <span class="ship-account-pill">{{ $carrier->is_dummy ? 'Dummy test' : 'Canlı kapalı' }}</span>
        </div>
      @empty <p class="admin-description">Henüz bağlı kargo firması yok. DHL, Dummy Kargo veya başka firmaları Kargo Entegratör panelinden ekle.</p>
      @endforelse
    </div>
    <div class="ship-list-panel">
      <h3>Gönderici depolar <small>{{ $warehouses->count() }}</small></h3>
      @forelse($warehouses as $warehouse)
        <div class="ship-account-row"><span><strong>{{ $warehouse->name }}</strong>
          <small>Depo ID: {{ $warehouse->remote_id }}</small></span></div>
      @empty <p class="admin-description">Gönderici depo tanımlanmadı. Kargo Entegratör panelinden bir depo adresi ekle.</p>
      @endforelse
    </div>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>3. Dummy Kargo test işlemleri</h2></div>
  <p class="admin-description">Yalnızca ödemesi doğrulanmış <strong>test siparişlerinde</strong> Dummy Kargo barkodu oluşturulabilir. Gerçek DHL, Aras, Yurtiçi vb. gönderilerine izin verilmez.</p>
  @if($testAllowed && $configured && $lastCheckedAt)
    <form method="POST" action="{{ route('admin.shipping-integrations.toggle') }}">
      @csrf
      <input type="hidden" name="enabled" value="{{ $enabled ? '0' : '1' }}">
      <button class="{{ $enabled ? 'admin-secondary' : 'admin-primary' }}" type="submit"><x-admin-icon name="check"/>
        {{ $enabled ? 'Dummy Kargo testlerini kapat' : 'Dummy Kargo testlerini etkinleştir' }}
      </button>
    </form>
  @else
    <p class="ship-info">Bağlantı kurulup en az bir Dummy Kargo hesabı ve depo senkronize edildikten sonra test etkinleştirilebilir. Yalnızca Hostinger test alan adında kullanılabilir.</p>
  @endif
  <div class="ship-callback-line">
    <strong>Kargo durum bildirim adresi</strong>
    <code>{{ route('shipping.callback') }}</code>
    <small>Gönderi oluşturulurken otomatik tanımlanır. HMAC-SHA512 imzası kontrol edilmeden sipariş durumu değişmez.</small>
  </div>
</section>

<section class="admin-panel">
  <div class="admin-panel-title"><h2>Kurulum rehberi</h2></div>
  <ol class="ship-steps">
    <li><a href="https://app.kargoentegrator.com/" target="_blank" rel="noopener noreferrer">Kargo Entegratör paneli</a> → Ayarlar → Satış Kanalları / API sayfasından API anahtarını al.</li>
    <li>Panelde <strong>Kargo Hesapları</strong> bölümünde DHL anlaşmanı tanımla; test için <strong>Dummy Kargo</strong> ekle.</li>
    <li><strong>Depo Adresleri</strong> bölümünde gönderici deponu tanımla.</li>
    <li>Buraya dön ve <strong>Bağlantıyı kontrol et ve hesapları yenile</strong> butonuna bas.</li>
    <li>Dummy testini aç; ödeme doğrulanmış bir test siparişinde <strong>Kargo Oluştur</strong> işlemini dene.</li>
    <li>Dummy Kargo durumunu sağlayıcı panelinden değiştirip NEWTIC'teki takibi kontrol et.</li>
  </ol>
  <p class="admin-description">Bu ekran gönderici depo veya DHL sözleşmesi açmaz. Kargo firması kimlik bilgileri Kargo Entegratör panelinde yönetilir; NEWTIC yalnızca entegratör API anahtarına ihtiyaç duyar.</p>
  <a class="admin-secondary" href="https://dev.kargoentegrator.com/" target="_blank" rel="noopener noreferrer"><x-admin-icon name="external-link"/> Resmî API dokümantasyonu</a>
</section>
@endsection
