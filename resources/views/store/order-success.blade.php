@extends('layouts.store')
@section('title', $tracking['title'].' | Polaris Kurşun')
@section('content')
<main class="checkout-shell checkout-success-page" id="content">
  <div class="checkout-top"><a href="{{ route('home') }}" aria-label="Ana sayfa">‹</a><strong>Sipariş Bilgileri</strong><span></span></div>
  <div class="checkout-card checkout-success-hero order-tracking-hero stage-{{ $tracking['key'] }}"
    data-order-stage="{{ $tracking['key'] }}" @if($tracking['key'] === 'pending_payment') data-order-celebration="{{ $order->id }}" @endif>
    @if($tracking['icon'] === 'party')
      <div class="checkout-success-mark" role="img" aria-label="Sipariş kutlaması">🎉</div>
    @elseif($tracking['icon'] === 'thumb')
      <div class="order-stage-mark order-stage-thumb" role="img" aria-label="Sipariş tamamlandı">👍</div>
    @elseif($tracking['icon'] === 'truck')
      <div class="order-stage-mark order-stage-truck" role="img" aria-label="Kargo aracı">
        <span class="order-truck-wind" aria-hidden="true"></span><x-store-icon name="truck" />
      </div>
    @elseif($tracking['icon'] === 'package')
      <div class="order-stage-mark order-stage-package" role="img" aria-label="Paket hazırlanıyor"><x-store-icon name="package" /></div>
    @elseif($tracking['icon'] === 'review')
      <div class="order-stage-mark order-stage-review" role="img" aria-label="Ödeme kontrolü"><x-store-icon name="landmark" /></div>
    @elseif($tracking['icon'] === 'alert' || $tracking['icon'] === 'x')
      <div class="order-stage-mark order-stage-alert" role="img" aria-label="{{ $tracking['badge'] }}"><x-store-icon name="x" /></div>
    @else
      <div class="order-stage-mark order-stage-clock" role="img" aria-label="Durum bekleniyor"><x-store-icon name="clock-3" /></div>
    @endif
    <h1>{{ $tracking['title'] }}</h1>
    <p class="order-stage-description">{{ $tracking['description'] }}</p>
    <p class="order-stage-number">Sipariş numaran: <strong>{{ $order->order_number }}</strong></p>
    <span class="checkout-status order-stage-status">{{ $tracking['badge'] }}</span>
    @if($order->is_test)
      <p class="checkout-testing-note"><strong>Test siparişi:</strong> Bu sipariş yalnızca deneme amaçlıdır. Gerçek havale/EFT göndermeyin; durum etiketleri gerçek banka tahsilatı yapıldığını göstermez.</p>
    @endif
    @guest
      <div class="payment-guest-link">
        <div><strong>Sipariş bağlantını sakla</strong><small>14 gün boyunca ödeme talimatlarına ve sipariş durumuna buradan dönebilirsin. Bu özel bağlantıyı başkalarıyla paylaşma.</small></div>
        <button type="button" class="payment-copy-button" data-copy-value="{{ $paymentReturnUrl }}" data-copy-label="Sipariş takip bağlantısı" aria-label="Sipariş bağlantısını kopyala"><x-store-icon name="copy" /></button>
      </div>
    @endguest
  </div>
  <section class="checkout-card order-tracking-timeline" aria-labelledby="order-tracking-title">
    <div class="order-tracking-heading">
      <div>
        <span class="payment-eyebrow">SİPARİŞ TAKİBİ</span>
        <h2 id="order-tracking-title">Siparişinizin yolculuğu</h2>
      </div>
      <a href="{{ request()->fullUrl() }}" class="order-tracking-refresh" aria-label="Sipariş durumunu yenile"><x-store-icon name="clock-3" /> Yenile</a>
    </div>
    <ol class="order-tracking-steps">
      @foreach($tracking['stages'] as $index => $stage)
        <li class="order-tracking-step is-{{ $stage['state'] }}" @if($stage['state'] === 'current' && $tracking['key'] !== 'cancelled') aria-current="step" @endif>
          <span class="order-step-point" aria-hidden="true">
            @if($stage['state'] === 'done')<x-store-icon name="check" />@else{{ $index + 1 }}@endif
          </span>
          <span class="order-step-text">{{ $stage['name'] }}</span>
        </li>
      @endforeach
    </ol>
    @if($tracking['key'] === 'cancelled')
      <p class="order-tracking-note is-cancelled">Sipariş iptal edildiği için takip adımları durduruldu.</p>
    @elseif($tracking['key'] === 'out_for_delivery')
      <p class="order-tracking-note is-active"><x-store-icon name="truck" /> Kargonuz dağıtıma çıkarıldı; teslimat için yolda.</p>
    @elseif($tracking['key'] === 'payment_review')
      <p class="order-tracking-note">Ödeme bildiriminiz alındı, banka onayı henüz tamamlanmadı.</p>
    @endif
  </section>

  @if(session('success'))<div class="cart-flash is-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="cart-flash is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

  <section class="checkout-card payment-instructions" aria-labelledby="payment-instructions-title">
    <div class="payment-instructions-heading">
      <div><span class="payment-eyebrow">HAVALE / EFT</span><h2 id="payment-instructions-title">{{ $order->status === 'pending_payment' ? 'Ödeme bilgileri' : 'Ödeme durumu' }}</h2></div>
      <span class="payment-status-pill {{ in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true) ? 'is-verified' : '' }} {{ $order->status === 'cancelled' ? 'is-cancelled' : '' }}">{{ $tracking['payment'] }}</span>
    </div>
    @if($order->is_test)
      <p class="checkout-testing-note"><strong>TEST SİPARİŞİ:</strong> Bu ekran yalnızca ödeme akışını denemek içindir. Gerçek havale/EFT göndermeyin. “Ödeme Yaptım” işlemi test bildirimi oluşturur.</p>
    @endif
    @if($order->status === 'pending_payment')
      @if($latestPaymentNotification?->status !== 'pending')
        <p class="payment-guide">Aşağıdaki banka hesaplarından birini seçin. Havale/EFT açıklamasına <strong>yalnızca sipariş numaranızı</strong> yazın ve <strong>toplam tutarı</strong> gönderin. Ödeme sonrası bildiriminizi iletin; banka kontrolü tamamlanana kadar sipariş ödeme bekliyor durumundadır.</p>
      @endif
      <div class="payment-copy-grid">
        <div class="payment-copy-item">
          <span>Gönderilecek tutar</span>
          <strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong>
        </div>
        <div class="payment-copy-item">
          <span>Açıklamaya yazılacak sipariş numarası</span>
          <div class="payment-copy-line"><strong id="payment-order-code">{{ $order->order_number }}</strong>
            <button type="button" class="payment-copy-button" data-copy-value="{{ $order->order_number }}" data-copy-label="Sipariş numarası" aria-label="Sipariş numarasını kopyala" title="Sipariş numarasını kopyala"><x-store-icon name="copy" /></button>
          </div>
        </div>
      </div>
      @if($latestPaymentNotification?->status === 'rejected')
        <p class="payment-rejected"><strong>Önceki bildiriminiz doğrulanamadı.</strong>
          @if($latestPaymentNotification->admin_note) {{ $latestPaymentNotification->admin_note }} @endif
          Banka hareketini kontrol edip tekrar bildirebilirsiniz.</p>
      @endif
      @if($latestPaymentNotification?->status === 'pending')
        <p class="payment-review-status"><x-store-icon name="clock-3" /> <strong>Ödeme bildiriminiz alındı.</strong> Banka hareketleri kontrol ediliyor. Tekrar bildirim göndermeniz gerekmez.</p>
      @elseif($bankAccounts->isEmpty())
        <p class="payment-awaiting-bank">Banka hesapları henüz tanımlanmadı. Hesap bilgileri aktif hale gelene kadar ödeme göndermeyin.</p>
      @else
        <h3 class="payment-bank-title">Banka hesabını seç</h3>
        <form id="payment-notification-form" method="POST" action="{{ $paymentNotificationAction }}">
          @csrf
          <div class="payment-bank-list">
            @foreach($bankAccounts as $bank)
              <div class="payment-bank-card">
                <label class="payment-bank-select">
                <input type="radio" name="bank_account_id" value="{{ $bank->id }}" @checked(old('bank_account_id', $bankAccounts->first()->id) == $bank->id) required>
                <span class="payment-bank-logo">
                  @if($bank->logo_path)<img src="{{ asset($bank->logo_path) }}" width="56" height="56" alt="{{ $bank->bank_name }} logosu" loading="lazy">
                  @else<x-store-icon name="landmark" />@endif
                </span>
                <span class="payment-bank-information">
                  <strong>{{ $bank->bank_name }}</strong>
                  <small>{{ $bank->account_holder }}</small>
                  <span class="payment-bank-iban">{{ trim(chunk_split($bank->iban, 4, ' ')) }}</span>
                </span>
                </label>
                <button class="payment-copy-button" type="button" data-copy-value="{{ $bank->iban }}" data-copy-label="{{ $bank->bank_name }} IBAN" aria-label="{{ $bank->bank_name }} IBAN numarasını kopyala" title="IBAN'ı kopyala"><x-store-icon name="copy" /></button>
              </div>
            @endforeach
          </div>

            <label class="checkout-field payment-note-label"><span>Ödeme yapan kişi / not (isteğe bağlı)</span>
              <input name="customer_note" maxlength="500" value="{{ old('customer_note') }}" placeholder="Örn. Havale farklı bir isimden yapıldı">
            </label>
            <button type="submit" class="checkout-action payment-notify-button">{{ $order->is_test ? 'Ödeme Yaptım (Test Bildirimi)' : 'Ödeme Yaptım – Bildir' }}</button>
            <small class="payment-verify-note">Bildiriminiz ödeme onayı değildir. Sipariş, banka hareketleri doğrulanınca hazırlanmaya alınır.</small>
        </form>
      @endif
    @elseif($order->status === 'cancelled')
      <p class="payment-rejected">Sipariş iptal edildi. Bu sipariş için artık ödeme bildirimi yapılamaz.</p>
    @elseif(in_array($order->status, ['preparing','shipped','out_for_delivery','completed'], true))
      <p class="payment-review-status is-verified"><x-store-icon name="check" />
        @if($latestPaymentNotification?->status === 'verified')
          Ödemeniz doğrulandı. Siparişinizin güncel durumu: <strong>{{ $tracking['badge'] }}.</strong>
        @else
          Ödeme aşaması tamamlandı olarak kaydedilmiş. Güncel sipariş durumu: <strong>{{ $tracking['badge'] }}.</strong>
        @endif
      </p>
    @else
      <p class="payment-review-status">Siparişinizin durumu: {{ $order->statusLabel() }}.</p>
    @endif
    <span class="payment-copy-live sr-only" role="status" aria-live="polite" data-payment-copy-status></span>
    <div class="payment-copy-toast" data-payment-copy-toast hidden aria-hidden="true"></div>
  </section>
  @if(in_array($order->status, ['shipped', 'out_for_delivery', 'completed'], true))
  <section class="checkout-card order-shipping-card" id="shipment-status" aria-labelledby="shipment-title">
    <div class="order-tracking-heading"><div><span class="payment-eyebrow">KARGO TAKİBİ</span><h2 id="shipment-title">Kargo bilgileri</h2></div><x-store-icon name="truck" /></div>
    <p class="order-shipping-description">
      @if($order->status === 'out_for_delivery')Paketiniz dağıtıma çıkarıldı.
      @elseif($order->status === 'shipped')Paketiniz kargo sürecinde.
      @else Siparişiniz tamamlandı.@endif
    </p>
    @if($order->carrier_name)
      <div class="order-shipping-line"><span>Kargo firması</span><strong>{{ $order->carrier_name }}</strong></div>
    @endif
    @if($order->tracking_number)
      <div class="order-shipping-line"><span>Takip numarası</span>
        <div><strong>{{ $order->tracking_number }}</strong>
          <button type="button" class="payment-copy-button" data-copy-value="{{ $order->tracking_number }}" data-copy-label="Kargo takip numarası" aria-label="Kargo takip numarasını kopyala"><x-store-icon name="copy" /></button>
        </div>
      </div>
    @else
      <p class="order-shipping-pending">Kargo takip numarası henüz paylaşılmadı. Eklendiğinde burada görüntülenecek.</p>
    @endif
    @if($order->tracking_url && str_starts_with($order->tracking_url, 'https://'))
      <a class="checkout-outline-action" href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer nofollow"><x-store-icon name="truck" /> Kargo firmasından takip et</a>
    @endif
  </section>
  @endif
  <div class="checkout-card">
    <h2>Sipariş detayları</h2>
    @foreach($order->items as $line)
      <div class="checkout-order-item"><span>{{ $line->product_name }} @if($line->option_value)<small>· {{ $line->option_label }}: {{ $line->option_value }}</small>@endif <small>× {{ $line->quantity }}</small></span>
        <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
      @if($order->status === 'completed' && auth()->check() && $order->user_id === auth()->id() && $line->product?->is_active)
        <a class="order-review-link" href="{{ route('product.show', $line->product->slug) }}#urun-yorumlari">
          <x-store-icon name="sparkles" /> Ürünü değerlendir <x-store-icon name="arrow-right" />
        </a>
      @endif
    @endforeach
    <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
    <div class="checkout-price-line"><span>Kargo</span><strong>{{ $order->shipping_cents === 0 ? 'Ücretsiz' : number_format($order->shipping_cents / 100, 2, ',', '.').' ₺' }}</strong></div>
    <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
  </div>
  <div class="checkout-card"><h2>Teslimat bilgileri</h2>
    <p>{{ $order->customer_name }} · {{ $order->phone }}</p><p>{{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</p>
    @if($order->invoice_type === 'corporate')<p>Kurumsal fatura: {{ $order->company_title }}</p>@endif
    @if($order->order_note)<p><strong>Sipariş notu:</strong> {{ $order->order_note }}</p>@endif
    <div class="checkout-payment-option"><strong>Havale / EFT · {{ $tracking['payment'] }}</strong></div>
    <p class="checkout-muted">{{ $order->status === 'pending_payment' ? 'Banka kontrolü tamamlanana kadar ödeme onaylanmış sayılmaz.' : 'Güncel ödeme ve sipariş durumunu yukarıdaki takip alanından görebilirsiniz.' }}</p>
  </div>
  @if($order->status === 'pending_payment')
    <a class="checkout-action order-tracking-cta" href="#payment-instructions-title">{{ $tracking['key'] === 'payment_review' ? 'Ödeme kontrol durumunu gör' : 'Ödeme bilgilerine git' }}</a>
  @elseif(in_array($order->status, ['shipped','out_for_delivery'], true))
    @if($order->tracking_url && str_starts_with($order->tracking_url, 'https://'))
      <a class="checkout-action order-tracking-cta" href="{{ $order->tracking_url }}" target="_blank" rel="noopener noreferrer nofollow">Kargoyu takip et <x-store-icon name="truck" /></a>
    @else
      <a class="checkout-action order-tracking-cta" href="#shipment-status">Kargo bilgilerini görüntüle <x-store-icon name="truck" /></a>
    @endif
  @elseif($order->status === 'cancelled')
    <a class="checkout-action order-tracking-cta" href="{{ route('home') }}">Alışverişe devam et</a>
  @else
    <a class="checkout-action order-tracking-cta" href="{{ auth()->check() ? route('account.index') : route('home') }}">{{ auth()->check() ? 'Siparişlerime dön' : 'Alışverişe dön' }}</a>
  @endif
</main>
@endsection
