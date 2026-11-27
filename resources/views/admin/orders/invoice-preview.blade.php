@extends('admin.layout')
@section('title','Fatura hesaplama ön izlemesi | NEWTIC')
@section('content')
@php($money = fn($cents) => $cents === null ? '—' : number_format($cents / 100,2,',','.').' ₺')
<div class="admin-page-heading">
  <div><a class="admin-back" href="{{ route('admin.orders.show',$order) }}">← Siparişe dön</a><h1>Fatura hesaplama ön izlemesi</h1><p>{{ $order->order_number }} · {{ $order->statusLabel() }}</p></div>
</div>
<section class="admin-panel">
  <h2>Belge düzenlenmedi</h2>
  <p>Bu ekran yalnızca kayıtlı sipariş tutarlarını kontrol eder. Resmî fatura değildir; EDM'e gönderilmez, fatura numarası alınmaz ve müşteri bilgilendirilmez.</p>

  @if(!$payment['payment_verified'])<p role="status">Ödeme doğrulanmadı. Fatura gönderimine uygunluk değerlendirilmedi.</p>@endif
  @if($order->status === 'cancelled')<p role="status">İptal edilmiş sipariş. Faturalandırma yapılmaz.</p>@endif
  @if($payment['provider_difference_cents']>0)
    <div class="admin-detail-line"><span>Ödeme sağlayıcısı farkı (ayrı)</span><strong>{{ $money($payment['provider_difference_cents']) }}</strong></div>
    <p>Bu farkın vergisel niteliği doğrulanmadan satış tutarına eklenmez.</p>
  @endif
</section>
@if($calculationError)
  <section class="admin-panel" style="margin-top:16px"><h2>Hesaplama doğrulanamadı</h2><p role="alert">{{ $calculationError }}</p></section>
@else
  @if($order->shipping_cents>0)
    <section class="admin-panel" style="margin-top:16px">
      <h2>Kargo KDV varsayımı</h2>
      <p>Kargonun uygulanacak vergi oranı henüz sistemde kesinleştirilmedi. Aşağıdaki seçim yalnızca hesaplama denemesidir; siparişi değiştirmez.</p>
      <form method="GET" action="{{ route('admin.orders.invoice-preview',$order) }}" class="admin-form">
        <label>Ön izleme oranı<select name="shipping_rate" required><option value="" disabled @selected($shippingRate===null)>Oran seçilmedi</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}" @selected($shippingRate===(string)$rate)>%{{ $rate }}</option>@endforeach</select></label>
        <button type="submit" class="admin-secondary">Hesapla</button>
        <a href="{{ route('admin.orders.invoice-preview',$order) }}">Varsayımı kaldır</a>
      </form>
    </section>
  @endif
  <section class="admin-panel" style="margin-top:16px">
    <h2>Fatura kalemleri</h2>
    @foreach($preview['lines'] as $line)
      <div class="admin-order-row">
        <span><strong>{{ $line['label'] }}</strong><small>{{ $line['quantity'] }} adet · {{ $money($line['unit_gross_cents']) }} / adet</small><small>{{ $line['rate']===null ? 'KDV oranı bilinmiyor' : 'KDV %'.$line['rate'] }} · Matrah {{ $money($line['net_cents']) }} · KDV {{ $money($line['tax_cents']) }}</small></span>
        <strong>{{ $money($line['gross_cents']) }}</strong>
      </div>
    @endforeach
  </section>
  <section class="admin-panel" style="margin-top:16px">
    <h2>KDV özeti</h2>
    @foreach($preview['groups'] as $group)
      <div class="admin-detail-line"><span>%{{ $group['rate'] }} matrah</span><strong>{{ $money($group['net_cents']) }}</strong></div>
      <div class="admin-detail-line"><span>%{{ $group['rate'] }} KDV</span><strong>{{ $money($group['tax_cents']) }}</strong></div>
    @endforeach
    @if(!$preview['complete'])
      <div class="admin-detail-line"><span>KDV bilgisi eksik tutar</span><strong>{{ $money($preview['unclassified_cents']) }}</strong></div>
      <p role="alert">Vergi hesabı eksik. Geçmiş ürünlerin KDV oranları tahmin edilmez.</p>
    @endif
    @if($preview['shipping_assumption'])<p>Seçilen kargo KDV oranı yalnızca varsayımdır.</p>@endif
    <div class="admin-detail-line"><span>Toplam matrah</span><strong>{{ $money($preview['net_cents']) }}</strong></div>
    <div class="admin-detail-line"><span>Toplam KDV</span><strong>{{ $money($preview['tax_cents']) }}</strong></div>
    <div class="admin-detail-line"><span>KDV dahil sipariş toplamı</span><strong>{{ $money($preview['gross_cents']) }}</strong></div>
    <p>Matrah satır bazında kuruşa yuvarlanır; KDV, brüt tutardan matrah çıkarılarak hesaplanır. Bu hesap yöntemi EDM belge doğrulamasından önce yeniden test edilecektir.</p>
  </section>
@endif
<section class="admin-panel" style="margin-top:16px">
  <h2>Yerel teknik fatura taslağı</h2>
  <p>Bu taslak resmî belge değildir. EDM'e gönderilmez, mali fatura numarası almaz ve müşteriye gösterilmez. XML yalnızca vergi ve tutar bileşenlerini içerir; tam UBL-TR faturası değildir.</p>
  @if($preparation)
    <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>Ayrılan ETTN</span><strong>{{ $preparation->uuid }}</strong></div>
    <div class="admin-detail-line"><span>Taslak toplamı</span><strong>{{ $money($preparation->gross_cents) }}</strong></div>
    <div class="admin-detail-line"><span>Durum</span><strong>Yerel taslak — gönderilmedi</strong></div>
    @if($preparation->shipping_rate !== null)<p>Kargo oranı %{{ $preparation->shipping_rate }}; yalnızca teknik varsayım.</p>@endif
    <a class="admin-secondary" href="{{ route('admin.orders.invoice-preparation.xml',$order) }}">Teknik XML indir</a>
    <p>İlk hesap kaydı sabit tutulur; yeni sorgular ikinci ETTN oluşturmaz. Sipariş değişiklikleri gönderim aşamasından önce yeniden doğrulanacaktır.</p>
  @elseif($preview && $preview['complete'] && $order->status !== 'cancelled')
    <form method="POST" action="{{ route('admin.orders.invoice-preparation.store',$order) }}" class="admin-form">
      @csrf
      @if($shippingRate !== null)<input type="hidden" name="shipping_rate" value="{{ $shippingRate }}">@endif
      <button class="admin-primary" type="submit">Yerel teknik taslak oluştur</button>
    </form>
  @else
    <p>Geçerli KDV hesabı ve iptal edilmemiş sipariş gereklidir.</p>
  @endif
</section>
@if(in_array($order->status,\App\Services\Invoices\InvoiceDispatch::STATUSES,true) && $payment['payment_verified'])
<section class="admin-panel">
  <h2>Fatura işlemleri</h2>
  @php($dispatch=\Illuminate\Support\Facades\DB::table('invoice_dispatch_requests')->where('order_id',$order->id)->first())
  @if($dispatch)
    <p>Fatura oluşturma isteği mevcut. <a href="{{ route('admin.invoices.index') }}">İşlem merkezini aç</a></p>
  @else
    <form class="admin-form" method="POST" action="{{ route('admin.invoices.create') }}">@csrf
      <input type="hidden" name="orders[]" value="{{ $order->id }}">
      @if($shippingRate!==null)<input type="hidden" name="shipping_rate" value="{{ $shippingRate }}">@endif
      @if($missingItems->isNotEmpty())
        <p>Geçmiş KDV kaydı eksik ürünler:</p>
        @foreach($missingItems as $item)<small>{{ $item->product_name }} — Güncel KDV: {{ $item->product?->vat_rate!==null ? '%'.$item->product->vat_rate : 'Bilinmiyor' }}</small>@endforeach
        <label>Eksik ürün KDV oranını onayla<select name="missing_product_rate" required><option value="">Oran seçin</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>
      @endif
      <button type="submit" class="admin-primary" @disabled(!\App\Services\Invoices\InvoiceDispatch::staging())>Fatura oluştur</button>
    </form>
  @endif
</section>
@endif
@endsection
