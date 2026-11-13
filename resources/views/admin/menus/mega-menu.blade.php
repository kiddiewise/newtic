@extends('admin.layout')
@section('title', 'Mega Menü Ayarları | NEWTIC')
@section('topbar', 'Menüler / Mega Menü Ayarları')
@section('content')
<div class="admin-page-heading">
  <p class="admin-eyebrow">MENÜLER</p>
  <h1>Mega menü ayarları</h1>
  <p>Mobil menü afişini, sosyal medya bağlantılarını, WhatsApp desteğini ve alt yazıyı buradan yönetebilirsiniz. Boş bırakılan bağlantılar menüde görünmez.</p>
</div>

<form class="admin-form mega-menu-settings-form" action="{{ route('admin.menus.mega-menu.update') }}" method="POST" enctype="multipart/form-data">
  @csrf
  @method('PATCH')

  @if($errors->any())
    <div class="admin-panel" role="alert">
      <strong>Lütfen aşağıdaki bilgileri kontrol edin:</strong>
      <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
    </div>
  @endif

  <section class="admin-panel">
    <div class="admin-panel-title"><h2>Mega menü üst görseli</h2></div>
    <p class="admin-description">“Avın her anına hazır ol” alanının yerine aynı yükseklikte yalnızca görsel gösterilir. Görsel yüklenmezse alan gizlenir.</p>
    @if($settings->hero_image_path)
      <img class="mega-admin-preview" src="{{ asset($settings->hero_image_path) }}" alt="Menüde kullanılan görsel" width="420" height="262">
    @else
      <p class="admin-description">Henüz mega menü görseli eklenmedi.</p>
    @endif
    <div class="mega-menu-form-fields">
      <label class="wide">Görsel yükle
        <input type="file" name="hero_image" accept="image/png,image/jpeg,image/webp,image/avif">
        <small>Önerilen boyut: 1200 × 750 piksel. PNG, JPG, WebP veya AVIF, en çok 5 MB. Görsel menü ölçülerine sığdırılarak kırpılabilir.</small>
      </label>
      <label class="admin-check wide"><input type="checkbox" name="remove_hero_image" value="1"> Mevcut mega menü görselini kaldır</label>
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-title"><h2>Sosyal medya bağlantıları</h2></div>
    <p class="admin-description">Yalnızca bağlantı girdiğiniz platformlar görünür. Sosyal medya butonları koyu kurumsal renkte ve kompakt boyuttadır.</p>
    <div class="mega-menu-form-fields">
      <label>Instagram
        <input type="url" name="instagram_url" maxlength="255" value="{{ old('instagram_url', $settings->instagram_url) }}" placeholder="https://instagram.com/...">
      </label>
      <label>YouTube
        <input type="url" name="youtube_url" maxlength="255" value="{{ old('youtube_url', $settings->youtube_url) }}" placeholder="https://youtube.com/@...">
      </label>
      <label>Facebook
        <input type="url" name="facebook_url" maxlength="255" value="{{ old('facebook_url', $settings->facebook_url) }}" placeholder="https://facebook.com/...">
      </label>
      <label>X (Twitter)
        <input type="url" name="x_url" maxlength="255" value="{{ old('x_url', $settings->x_url) }}" placeholder="https://x.com/...">
      </label>
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-title"><h2>WhatsApp desteği</h2></div>
    <p class="admin-description">Numara boş bırakıldığında WhatsApp destek butonu tamamen gizlenir.</p>
    <div class="mega-menu-form-fields">
      <label>WhatsApp telefon numarası
        <input type="tel" name="whatsapp_number" maxlength="25" value="{{ old('whatsapp_number', $settings->whatsapp_number) }}" placeholder="+90 5XX XXX XX XX">
      </label>
      <label class="wide">WhatsApp hazır mesajı
        <textarea name="whatsapp_message" rows="3" maxlength="500" placeholder="Merhaba, ürünleriniz hakkında bilgi almak istiyorum.">{{ old('whatsapp_message', $settings->whatsapp_message) }}</textarea>
        <small>Ziyaretçi WhatsApp butonuna dokunduğunda bu metin mesaj alanına otomatik yazılır. Boş bırakırsanız sohbet doğrudan açılır.</small>
      </label>
    </div>
  </section>

  <section class="admin-panel">
    <div class="admin-panel-title"><h2>Menü alt yazısı</h2></div>
    <div class="mega-menu-form-fields">
      <label class="wide">En alttaki marka yazısı
        <input type="text" name="footer_text" maxlength="120" required value="{{ old('footer_text', $settings->footer_text) }}" placeholder="POLARIS KURŞUN • TÜRKİYE">
      </label>
    </div>
  </section>
  <div class="admin-form-actions"><button type="submit" class="admin-primary">Mega menü ayarlarını kaydet</button></div>
</form>
@endsection
