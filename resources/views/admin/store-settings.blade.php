@extends('admin.layout')
@section('title', 'Kargo Ayarları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">MAĞAZA AYARLARI</p><h1>Kargo ve ücretsiz kargo</h1>
    <p>Ücretsiz kargo limitini ve limit altı kargo ücretini buradan değiştirebilirsin.</p></div>
</div>

<div class="admin-panel">
  <div class="admin-panel-title"><h2>Kargo tutarları</h2></div>
  <form class="admin-form admin-settings-grid" method="POST" action="{{ route('admin.store-settings.update') }}">
    @csrf @method('PATCH')
    <label>Ücretsiz kargo limiti (₺)
      <input type="number" name="free_shipping_threshold" min="0" max="9999999999.99" step="0.01" required inputmode="decimal"
        value="{{ old('free_shipping_threshold', $settings->free_shipping_threshold) }}">
      <small>Sepetteki ürünlerin toplamı bu tutara eşit veya daha yüksekse kargo ücretsiz olur.</small>
    </label>
    <label>Limit altında uygulanacak kargo (₺)
      <input type="number" name="shipping_fee" min="0" max="9999999999.99" step="0.01" required inputmode="decimal"
        value="{{ old('shipping_fee', $settings->shipping_fee) }}">
      <small>Ürün toplamı limitin altında kaldığında bir kez eklenir; ürün başına değil.</small>
    </label>
    <div class="admin-form-actions"><button type="submit" class="admin-primary">Kargo ayarlarını kaydet</button></div>
  </form>
</div>
<div class="admin-panel">
  <div class="admin-panel-title"><h2>Örnek hesaplama</h2></div>
  <p class="admin-description">Şu an {{ number_format((float) $settings->free_shipping_threshold, 2, ',', '.') }} ₺ ve üzeri ücretsiz kargo. Daha düşük siparişlerde {{ number_format((float) $settings->shipping_fee, 2, ',', '.') }} ₺ kargo bedeli gösterilir.</p>
  <p class="admin-description">Bu kurallar sepette anında uygulanır. Sipariş alma/ödeme sistemi henüz geliştirme aşamasındadır.</p>
</div>
@endsection
