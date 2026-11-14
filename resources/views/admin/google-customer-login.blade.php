@extends('admin.layout')
@section('title', 'Google Giriş ve Üyelik | NEWTIC')
@section('topbar', 'Ayarlar / Google ile Giriş')
@section('content')
@php
  $images = array_values((array) (\App\Models\ShopAppearance::current()->auth_image_paths ?? []));
@endphp
<div class="admin-page-heading">
  <p class="admin-eyebrow">MAĞAZA AYARLARI / MÜŞTERİ KİMLİĞİ</p>
  <h1>Google ile giriş ve üyelik</h1>
  <p>Müşteri hesabı için yalnızca Google sosyal girişini yönet; mağaza görsellerini ve mobil deneyimi özelleştir.</p>
</div>

@if($errors->any())
  <div class="admin-alert admin-alert-error" role="alert"><strong>İşlem tamamlanamadı</strong>
    <ul>@foreach($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
  </div>
@endif

<section class="admin-panel google-login-admin-block">
  <div class="admin-panel-title"><h2>1. Google müşteri girişi</h2></div>
  <div class="google-login-admin-status">
    <span class="google-login-admin-led {{ $settings->available() ? 'is-ready' : '' }}"></span>
    <strong>{{ $settings->available() ? 'Google giriş açık' : ($settings->configured() ? 'Google ayarlandı · kapalı' : 'Google bağlantısı ayarlanmadı') }}</strong>
  </div>
  <p class="admin-description">Bu bağlantı <strong>yalnızca müşteri girişi</strong> içindir; Google Merchant Center ayarlarından tamamen ayrıdır. Her mağaza kendi Google Cloud OAuth istemcisini kullanır. Gerçek Google ile giriş, Google'da yetkilendirilmiş HTTPS domain ve doğru kimlik bilgileri sağlanmadan çalışmaz.</p>
  <div class="google-login-uri">
    <span>Google Cloud → OAuth web istemcisi → Authorized redirect URI</span>
    <code>{{ route('account.google.callback') }}</code>
    <span>Authorized JavaScript origins (gerekirse)</span>
    <code>{{ request()->getSchemeAndHttpHost() }}</code>
  </div>
  <form method="POST" action="{{ route('admin.google-login.update') }}" class="admin-form google-login-admin-form" autocomplete="off">
    @csrf @method('PATCH')
    <label>Google Client ID
      <input type="text" name="client_id" value="{{ old('client_id', $settings->client_id) }}" maxlength="255" required
        placeholder="...apps.googleusercontent.com" autocomplete="off">
    </label>
    <label>Google Client Secret
      <input type="password" name="client_secret" maxlength="2048" autocomplete="new-password"
        placeholder="{{ $settings->configured() ? 'Şifreli kayıtlı · Değiştirmek için yeni değer gir' : 'Google Client Secret' }}" @required(!$settings->configured())>
      <small>{{ $settings->configured() ? 'Kayıtlı ve şifreli; alanı boş bırakırsanız mevcut değer korunur.' : 'Yalnızca bu mağazanın sunucusunda şifrelenerek saklanır.' }}</small>
    </label>
    <label class="google-login-admin-toggle">
      <input type="hidden" name="enabled" value="0">
      <input type="checkbox" name="enabled" value="1" @checked(old('enabled', $settings->enabled))>
      <span><strong>Google ile giriş ve kayıt açık</strong><small>Kapalıyken müşteri ekranında Google butonu gizlenir. Klasik e-posta/şifre girişine dokunulmaz.</small></span>
    </label>
    <button class="admin-primary" type="submit">Google giriş ayarlarını kaydet</button>
  </form>
  <p class="admin-description">Güvenlik: mevcut e-postalı hesaplar Google'a otomatik bağlanmaz. Google'ın sabit kullanıcı kimliği kullanılır. Yönetici hesapları Google müşteri girişiyle açılamaz. Kimlik bilgilerini sohbet veya ekran görüntüsünde paylaşmayın.</p>
</section>

<section class="admin-panel google-login-admin-block">
  <div class="admin-panel-title"><h2>2. Giriş ve kayıt sayfası görselleri</h2></div>
  <p class="admin-description">Mobilde yumuşak gölgeli beyaz kart ve dikey görsel alanı, masaüstünde yan yana geniş kart görünümü kullanılır. İki sayfada aynı görseller görünür. Birden fazla görsel yüklenirse hafif yakınlaşma ve yumuşak geçiş otomatik çalışır.</p>
  <div class="google-login-image-spec">
    <strong>Önerilen görsel ölçüsü</strong>
    <span>1600 × 2000 piksel · 4:5 dikey oran</span>
    <span>Minimum öneri: 1200 × 1500 piksel</span>
    <span>WebP veya JPG · İdeal 1–2 MB · Yükleme üst sınırı 4 MB</span>
    <small>Görselin önemli içeriğini ortada bırakın; mobil ve masaüstünde kenarlardan hafif kırpılabilir. En fazla 5 görsel.</small>
  </div>
  <form method="POST" action="{{ route('admin.google-login.images') }}" enctype="multipart/form-data" class="admin-form google-login-admin-form">
    @csrf @method('PATCH')
    @if($images)
      <div class="google-login-image-gallery">
        @foreach($images as $index => $path)
          <label class="google-login-thumb">
            <img src="{{ asset($path) }}" alt="Giriş görseli {{ $index + 1 }}" loading="lazy">
            <span><input type="checkbox" name="remove[]" value="{{ $index }}"> Görseli kaldır</span>
          </label>
        @endforeach
      </div>
    @else
      <p class="admin-description">Henüz görsel yüklenmedi. Bu durumda sayfada genel, hafif animasyonlu renk geçişi gösterilir.</p>
    @endif
    <label>Yeni görseller ekle
      <input type="file" name="images[]" multiple accept=".jpg,.jpeg,.png,.webp,.avif,image/jpeg,image/png,image/webp,image/avif">
      <small>Mevcut görselleri kaldırarak yenileriyle değiştirebilirsiniz. Mobil ve masaüstü aynı dosyaları kullanır.</small>
    </label>
    <button type="submit" class="admin-primary">Görselleri kaydet</button>
  </form>
  <div class="google-login-admin-links">
    <a class="admin-secondary" href="{{ route('account.login') }}" target="_blank" rel="noopener noreferrer">Giriş sayfasını önizle</a>
    <a class="admin-secondary" href="{{ route('account.register') }}" target="_blank" rel="noopener noreferrer">Kayıt sayfasını önizle</a>
  </div>
</section>

<section class="admin-panel google-login-admin-block">
  <div class="admin-panel-title"><h2>3. Google Cloud kurulumu</h2></div>
  <p class="admin-description">Google Cloud'da OAuth consent screen ayarlarını tamamlayın. Web application türü OAuth Client oluşturun, yukarıdaki callback adresini tam olarak tanımlayın ve kendi domaininizi Google'da yetkilendirin. Test domainindeki yapılandırma gerçek domaine taşındığında yeni alan adını Google Cloud tarafında eklemeyi unutmayın.</p>
  <a href="https://console.cloud.google.com/auth/clients" class="admin-secondary" target="_blank" rel="noopener noreferrer">Google Cloud OAuth istemcileri</a>
  <p class="admin-description">E-posta gönderimi ve kurulum sihirbazı bu özelliğin kapsamına dahil değildir.</p>
</section>
@endsection