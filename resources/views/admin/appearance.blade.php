@extends('admin.layout')
@section('title', 'Logo ve Marka Görünümü | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <p class="admin-eyebrow">MARKA KİMLİĞİ</p>
  <h1>Mağaza logoları</h1>
  <p>Masaüstü ve mobil için farklı logo yükleyebilirsiniz. Siz yükleyene kadar NEWTIC varsayılan WebP logoları kullanılır.</p>
</div>
<div class="admin-panel">
  <form method="POST" action="{{ route('admin.appearance.update') }}" enctype="multipart/form-data" class="admin-form admin-settings-grid">
    @csrf @method('PATCH')
    <div class="admin-full admin-brand-preview">
      <h2>Masaüstü logosu</h2>
      @if($appearance->desktop_logo_path)
        <img src="{{ asset($appearance->desktop_logo_path) }}" alt="Yüklü masaüstü logosu" height="80">
      @else
        <img src="{{ asset(\App\Models\ShopAppearance::DEFAULT_DESKTOP_LOGO) }}" alt="Varsayılan NEWTIC masaüstü logosu" width="240" height="60">
        <p>Varsayılan NEWTIC yatay logosu kullanılıyor.</p>
      @endif
    </div>
    <label class="admin-full">
      Masaüstü logo dosyası
      <input type="file" name="desktop_logo" accept="image/png,image/jpeg,image/webp,image/avif">
      <small><strong>Önerilen: 800 × 200 px</strong> (4:1 yatay). Şeffaf PNG veya WebP; en çok 5 MB. Önemli logo öğelerini kenarlardan uzak tutun.</small>
    </label>
    <label class="admin-check admin-full"><input type="checkbox" name="remove_desktop_logo" value="1"> Masaüstü logosunu kaldır ve varsayılana dön</label>
    <div class="admin-full admin-brand-preview">
      <h2>Mobil logosu</h2>
      @if($appearance->mobile_logo_path)
        <img src="{{ asset($appearance->mobile_logo_path) }}" alt="Yüklü mobil logosu" height="80">
      @else
        <img src="{{ asset(\App\Models\ShopAppearance::DEFAULT_MOBILE_LOGO) }}" alt="Varsayılan NEWTIC mobil logosu" width="80" height="80">
        <p>Varsayılan NEWTIC kare logosu kullanılıyor.</p>
      @endif
    </div>
    <label class="admin-full">
      Mobil logo dosyası
      <input type="file" name="mobile_logo" accept="image/png,image/jpeg,image/webp,image/avif">
      <small><strong>Önerilen: 320 × 320 px</strong> (1:1 kare). Şeffaf PNG veya WebP; en çok 5 MB.</small>
    </label>
    <label class="admin-check admin-full"><input type="checkbox" name="remove_mobile_logo" value="1"> Mobil logosunu kaldır ve varsayılana dön</label>
    <div class="admin-form-actions"><button class="admin-primary" type="submit">Logoları kaydet</button></div>
  </form>
</div>
@endsection
