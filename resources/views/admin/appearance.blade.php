@extends('admin.layout')
@section('title', 'Logo ve Marka Görünümü | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <p class="admin-eyebrow">MARKA KİMLİĞİ</p>
  <h1>Mağaza logoları</h1>
  <p>Masaüstü ve mobil için farklı logo yükleyebilirsiniz. İkisini de boş bırakırsanız mevcut Polaris simgesi kullanılır.</p>
</div>
<div class="admin-panel">
  <form method="POST" action="{{ route('admin.appearance.update') }}" enctype="multipart/form-data" class="admin-form admin-settings-grid">
    @csrf @method('PATCH')
    <div class="admin-full admin-brand-preview">
      <h2>Masaüstü logosu</h2>
      @if($appearance->desktop_logo_path)
        <img src="{{ asset($appearance->desktop_logo_path) }}" alt="Yüklü masaüstü logosu" height="80">
      @else
        <p>Henüz özel logo yüklenmemiş. Mevcut Polaris yazı ve simgesi gösteriliyor.</p>
      @endif
    </div>
    <label class="admin-full">
      Masaüstü logo dosyası
      <input type="file" name="desktop_logo" accept="image/png,image/jpeg,image/webp,image/avif">
      <small>Öneri: şeffaf PNG veya WebP, yatay oran 4:1, örnek 800×200 px. En çok 5 MB.</small>
    </label>
    <label class="admin-check admin-full"><input type="checkbox" name="remove_desktop_logo" value="1"> Masaüstü logosunu kaldır ve varsayılana dön</label>
    <div class="admin-full admin-brand-preview">
      <h2>Mobil logosu</h2>
      @if($appearance->mobile_logo_path)
        <img src="{{ asset($appearance->mobile_logo_path) }}" alt="Yüklü mobil logosu" height="80">
      @else
        <p>Henüz özel logo yüklenmemiş. Mevcut Polaris simgesi gösteriliyor.</p>
      @endif
    </div>
    <label class="admin-full">
      Mobil logo dosyası
      <input type="file" name="mobile_logo" accept="image/png,image/jpeg,image/webp,image/avif">
      <small>Öneri: kare ve şeffaf PNG veya WebP, örnek 320×320 px. En çok 5 MB.</small>
    </label>
    <label class="admin-check admin-full"><input type="checkbox" name="remove_mobile_logo" value="1"> Mobil logosunu kaldır ve varsayılana dön</label>
    <div class="admin-form-actions"><button class="admin-primary" type="submit">Logoları kaydet</button></div>
  </form>
</div>
@endsection
