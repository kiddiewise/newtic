<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<title>{{ $order->order_number }} | {{ $mode === 'proforma' ? 'Promofatura' : 'Sipariş Hazırlama Formu' }}</title>
<style>
@page { size: A4 portrait; margin: 18mm 19mm 36mm; }
* { box-sizing: border-box; }
html, body { padding: 0; margin: 0; width: 100%; }
body { font-family: "DejaVu Sans", sans-serif; font-size: 9px; color: #132b41; line-height: 1.48; }
h1, h2, h3, p { padding: 0; margin: 0; }
table { border-spacing: 0; border-collapse: collapse; width: 100%; table-layout: fixed; }
.topline { height: 4px; background: #092037; margin-bottom: 13px; }
.header td { vertical-align: middle; }
.brand { width: 44%; }
.logo { max-width: 180px; max-height: 62px; }
.brand-fallback { font-size: 20px; font-weight: bold; letter-spacing: -.5px; }
.header-right { text-align: right; width: 56%; padding-left: 7px; }
.eyebrow { font-size: 7px; letter-spacing: .5px; font-weight: bold; color: #65798a; text-transform: uppercase; }
.document-title { font-size: 18px; letter-spacing: -.35px; font-weight: bold; margin-top: 3px; }
.subline { font-size: 8px; color: #697b89; margin-top: 2px; }
.meta { margin: 16px 0 13px; border: 1px solid #dbe3ea; background: #f7f9fb; }
.meta td { width: 25%; padding: 9px 10px 8px; border-right: 1px solid #dbe3ea; vertical-align: top; }
.meta td:last-child { border-right: 0; }
.caption { font-size: 8px; color: #6b8191; display: block; margin-bottom: 3px; letter-spacing: .2px; }
.value { font-size: 10px; font-weight: bold; word-wrap: break-word; }
.status { font-size: 9px; font-weight: bold; }
.section-head { font-weight: bold; font-size: 9px; text-transform: none; letter-spacing: .25px; color: #203c54; margin-bottom: 7px; }
.details { table-layout: fixed; margin-bottom: 14px; }
.details td { width: 50%; vertical-align: top; padding: 0; }
.details td:first-child { padding-right: 7px; }
.details td:last-child { padding-left: 7px; }
.detail-box { border: 1px solid #dde5eb; padding: 10px 11px; min-height: 100px; }
.detail-box .name { font-size: 10px; font-weight: bold; margin-bottom: 4px; }
.detail-box .line { margin: 3px 0; word-wrap: break-word; }
.detail-box .line b { color: #596d7e; font-weight: normal; }
.detail-box .subtle { color: #667b8b; font-size: 8px; margin-bottom: 5px; }
.heading-row { width: 100%; border-bottom: 2px solid #092037; padding-bottom: 6px; margin-top: 3px; margin-bottom: 0; }
.heading-row span { font-size: 9px; font-weight: bold; letter-spacing: .4px; }
.heading-row small { float: right; font-size: 8px; color: #778997; font-weight: normal; }
.items { width: 100%; table-layout: fixed; }
.items thead { display: table-header-group; }
.items th { overflow-wrap: anywhere; background: #edf2f6; color: #465c6e; font-weight: bold; font-size: 8px; padding: 8px 5px; text-align: left; border-bottom: 1px solid #d7e0e7; }
.items td { padding: 8px 5px; vertical-align: top; border-bottom: 1px solid #e5ebef; font-size: 9px; word-wrap: break-word; }
.items tr { page-break-inside: avoid; }
.items .rowno { color: #8ca0ad; font-size: 8px; }
.items .product { font-weight: normal; font-size: 9px; line-height: 1.5; }
.items .sku { font-size: 7.5px; color: #6c8190; margin-top: 3px; }
.items .option { font-size: 8px; margin-top: 2px; }
.items .qty { font-size: 12px; font-weight: bold; }
.items .money { text-align: right; font-size: 8px; white-space: normal; overflow-wrap: break-word; }
.items .check { text-align: center; color: #2b465c; font-size: 16px; line-height: 13px; }
.items th.money { text-align: right; }
.items th.check { text-align: center; font-size: 7px; }
.after-table { margin-top: 12px; }
.after-table td { vertical-align: top; }
.note-box { border-left: 3px solid #e99b59; background: #fff9f2; padding: 9px 11px; margin: 0 0 10px; }
.note-box .section-head { margin-bottom: 4px; color: #935b29; }
.note-text { color: #223e54; word-wrap: break-word; }
.ops-box { border: 1px solid #dbe3ea; padding: 10px 11px 9px; }
.ops-box .section-head { margin-bottom: 9px; }
.ops-line { margin-bottom: 7px; font-size: 9px; }
.sq { font-family: "DejaVu Sans", sans-serif; font-size: 13px; margin-right: 8px; color: #3a556a; }
.price-box { border: 1px solid #dbe3ea; margin-left: 3%; width: 97%; table-layout: fixed; }
.price-box td { padding: 8px 10px; border-bottom: 1px solid #e8eef2; font-size: 9px; }
.price-box td:first-child { width: 54%; } .price-box td:last-child { width: 46%; text-align: right; font-weight: bold; white-space: normal; }
.price-box tr:last-child td { background: #092037; border-bottom: 0; color: #fff; font-size: 11px; font-weight: bold; padding-top: 10px; padding-bottom: 10px; }
.payment-details { margin-top: 11px; font-size: 8px; color: #657889; }
.warning { border: 1px solid #eac6a4; color: #8b5328; background: #fff7ed; padding: 7px 10px; font-weight: bold; margin-bottom: 10px; }
.footer { position: fixed; left: 0; right: 0; bottom: 0; height: 32mm; border-top: 1px solid #cbd5de; padding-top: 7px; color: #70818f; }
.footer-main { width: 72%; }
.footer .doc-label { font-size: 9px; color: #233f56; font-weight: bold; margin-bottom: 3px; }
.footer .fineprint { font-size: 7.3px; line-height: 1.55; margin-top: 4px; }
.footer .qr { position: absolute; top: 1mm; right: 0; text-align: center; width: 29mm; height: 29mm; }
.footer .qr img { display: block; margin: 0 auto; width: 24mm; height: 24mm; }
.footer .qr span { display: block; font-size: 6.7px; font-weight: bold; color: #223e54; margin-top: 2px; }
</style>
</head>
<body>
  <div class="topline"></div>
  <table class="header">
    <tr>
      <td class="brand">
        @if($logoData)
          <img class="logo" src="{{ $logoData }}" alt="{{ $shopName }}">
        @else
          <div class="brand-fallback">{{ $shopName }}</div>
        @endif
      </td>
      <td class="header-right">
        <div class="eyebrow">{{ $shopName }} · OPERASYON</div>
        <h1 class="document-title">{{ $mode === 'proforma' ? 'PROMOFATURA' : 'SİPARİŞ HAZIRLAMA' }}</h1>
        <p class="subline">{{ $mode === 'proforma' ? 'Sipariş özeti / hazırlama nüshası' : 'Ürün toplama ve paketleme kontrol formu' }}</p>
      </td>
    </tr>
  </table>

  <table class="meta">
    <tr>
      <td><span class="caption">SİPARİŞ NO</span><span class="value">{{ $order->order_number }}</span></td>
      <td><span class="caption">SİPARİŞ TARİHİ</span><span class="value">{{ ($order->placed_at ?? $order->created_at)->format('d.m.Y H:i') }}</span></td>
      <td><span class="caption">SİPARİŞ DURUMU</span><span class="status">{{ $order->statusLabel() }}</span></td>
      <td><span class="caption">ÖDEME DURUMU</span><span class="status">{{ $paymentVerified ? 'Doğrulandı' : 'Bekliyor / doğrulanmadı' }}</span></td>
    </tr>
  </table>

  @if($order->status === 'cancelled')
    <div class="warning">SİPARİŞ İPTAL EDİLDİ · Ürünleri hazırlamayın.</div>
  @elseif(!$paymentVerified)
    <div class="warning">ÖDEME HENÜZ DOĞRULANMADI · Gönderim öncesinde ödeme kaydını kontrol edin.</div>
  @endif

  <table class="details">
    <tr>
      <td>
        <div class="detail-box">
          <h2 class="section-head">MÜŞTERİ BİLGİLERİ</h2>
          <p class="name">{{ $order->customer_name }}</p>
          <p class="line"><b>Telefon:</b> {{ $order->phone }}</p>
          <p class="line"><b>E-posta:</b> {{ $order->customer_email }}</p>
          <p class="line"><b>Ödeme:</b> {{ $order->payment_method === 'paytr' ? 'Kredi / Banka Kartı' : 'Havale / EFT' }}</p>
        </div>
      </td>
      <td>
        <div class="detail-box">
          <h2 class="section-head">TESLİMAT VE FATURA ADRESİ</h2>
          <p class="subtle">Fatura için siparişte kayıtlı teslimat adresi kullanılır.</p>
          @if($order->invoice_type === 'corporate' && $order->company_title)
            <p class="name">{{ $order->company_title }}</p>
            @if($order->tax_office || $order->tax_number)
              <p class="line"><b>Vergi:</b> {{ $order->tax_office }} {{ $order->tax_number ? '· '.$order->tax_number : '' }}</p>
            @endif
          @else
            <p class="name">{{ $order->customer_name }}</p>
            <p class="line"><b>Fatura türü:</b> Bireysel</p>
          @endif
          <p class="line">{{ $order->street_address }}</p>
          <p class="line">{{ $order->district }} / {{ $order->city }}</p>
        </div>
      </td>
    </tr>
  </table>

  <div class="heading-row">
    <span>SİPARİŞ KALEMLERİ</span>
    <small>{{ $order->items->sum('quantity') }} adet · {{ $order->items->count() }} kalem</small>
  </div>
  <table class="items">
    <thead>
      <tr>
        <th style="width:6%">#</th>
        <th style="width:{{ $mode === 'proforma' ? '43%' : '67%' }}">ÜRÜN / STOK KODU</th>
        <th style="width:9%;text-align:center">ADET</th>
        @if($mode === 'proforma')
          <th class="money" style="width:16%">BİRİM</th>
          <th class="money" style="width:17%">TUTAR</th>
        @endif
        <th class="check" style="width:9%">HAZIR</th>
      </tr>
    </thead>
    <tbody>
      @foreach($order->items as $index => $item)
        <tr>
          <td class="rowno">{{ $index + 1 }}</td>
          <td>
            <div class="product">{{ $item->product_name }}</div>
            @if($item->option_value)
              <div class="option">{{ $item->option_label ?: 'Seçenek' }}: <b>{{ $item->option_value }}</b></div>
            @endif
            <div class="sku">SKU: {{ $item->sku ?: '—' }}</div>
          </td>
          <td style="text-align:center"><span class="qty">{{ $item->quantity }}</span></td>
          @if($mode === 'proforma')
            <td class="money">{{ number_format($item->unit_price_cents / 100, 2, ',', '.') }} ₺</td>
            <td class="money"><b>{{ number_format($item->line_total_cents / 100, 2, ',', '.') }} ₺</b></td>
          @endif
          <td class="check">□</td>
        </tr>
      @endforeach
    </tbody>
  </table>

  <table class="after-table">
    <tr>
      <td style="width:{{ $mode === 'proforma' ? '57%' : '100%' }};padding-right:{{ $mode === 'proforma' ? '8px' : '0' }};">
        @if($order->order_note)
          <div class="note-box">
            <h3 class="section-head">MÜŞTERİ SİPARİŞ NOTU</h3>
            <div class="note-text">{{ $order->order_note }}</div>
          </div>
        @endif
        <div class="ops-box">
          <h3 class="section-head">HAZIRLIK KONTROLÜ</h3>
          <div class="ops-line"><span class="sq">□</span> Ürünler sayıldı ve toplandı</div>
          <div class="ops-line"><span class="sq">□</span> Ürünler / seçenekler kontrol edildi</div>
          <div class="ops-line"><span class="sq">□</span> Paketleme tamamlandı</div>
          <div class="ops-line"><span class="sq">□</span> Kargo etiketi eklendi</div>
          <p style="font-size:8px;color:#728592;margin-top:8px">Hazırlayan: ____________________</p>
        </div>
      </td>
      @if($mode === 'proforma')
        <td style="width:43%;">
          <table class="price-box">
            <tr><td>Ürünler toplamı</td><td>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</td></tr>
            <tr><td>Kargo</td><td>{{ $order->shipping_cents === 0 ? 'Ücretsiz' : number_format($order->shipping_cents / 100, 2, ',', '.').' ₺' }}</td></tr>
            <tr><td>GENEL TOPLAM</td><td>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</td></tr>
          </table>
          <p class="payment-details">Tutarlar sipariş oluşturulduğu andaki kayıttan alınmıştır. Kargo bedeli sipariş toplamına dahildir.</p>
        </td>
      @endif
    </tr>
  </table>

  <div class="footer">
    <div class="footer-main">
      <div class="doc-label">{{ $shopName }} · {{ $order->order_number }}</div>
      <p>Belge yalnızca mağaza içi sipariş hazırlama ve operasyon amacıyla düzenlenmiştir.</p>
      <p class="fineprint">
        {{ $mode === 'proforma'
            ? 'PROMOFATURA / TİCARİ BİLGİLENDİRME. E-FATURA, E-ARŞİV FATURA VEYA MALİ BELGE DEĞİLDİR.'
            : 'SİPARİŞ HAZIRLAMA FORMU. FİYAT VE TAHSİLAT BELGESİ DEĞİLDİR.' }}
      </p>
      <p class="fineprint">QR kodu okutarak ilgili siparişin yönetici sayfasını açabilirsin. Yetkili yönetici girişi gereklidir.</p>
    </div>
    <div class="qr">
      <img src="{{ $qrData }}" alt="Sipariş yönetici sayfası QR kodu">
      <span>QR · SİPARİŞİ AÇ</span>
    </div>
  </div>
</body>
</html>