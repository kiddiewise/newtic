@extends('layouts.store')
@section('title', 'Sipariş Alındı | Polaris Kurşun')
@section('content')
<main class="checkout-shell checkout-success-page" id="content">
  <div class="checkout-top"><a href="{{ route('home') }}" aria-label="Ana sayfa">‹</a><strong>Sipariş Bilgileri</strong><span></span></div>
  <div class="checkout-card checkout-success-hero" data-order-celebration="{{ $order->id }}">
    <div class="checkout-success-mark" role="img" aria-label="Sipariş kutlaması">🎉</div>
    <h1>Siparişin oluşturuldu!</h1>
    <p>Sipariş numaran: <strong>{{ $order->order_number }}</strong></p>
    <span class="checkout-status">{{ $order->statusLabel() }}</span>
    @if($order->is_test)
      <p class="checkout-testing-note"><strong>Test siparişi:</strong> Bu mağaza henüz test aşamasında. Gerçek banka ödemesi yapmayın; bu ekran ödeme alındığını göstermez.</p>
    @endif
    @guest
      <div class="payment-guest-link">
        <div><strong>Sipariş bağlantını sakla</strong><small>14 gün boyunca ödeme talimatlarına ve sipariş durumuna buradan dönebilirsin. Bu özel bağlantıyı başkalarıyla paylaşma.</small></div>
        <button type="button" class="payment-copy-button" data-copy-value="{{ $paymentReturnUrl }}" data-copy-label="Sipariş takip bağlantısı" aria-label="Sipariş bağlantısını kopyala"><x-store-icon name="copy" /></button>
      </div>
    @endguest
  </div>
  @if(session('success'))<div class="cart-flash is-success" role="status">{{ session('success') }}</div>@endif
  @if($errors->any())<div class="cart-flash is-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif

  <section class="checkout-card payment-instructions" aria-labelledby="payment-instructions-title">
    <div class="payment-instructions-heading">
      <div><span class="payment-eyebrow">HAVALE / EFT</span><h2 id="payment-instructions-title">Ödeme bilgileri</h2></div>
      <span class="payment-status-pill {{ $order->status === 'preparing' ? 'is-verified' : '' }}">{{ $order->statusLabel() }}</span>
    </div>
    @if($order->is_test)
      <p class="checkout-testing-note"><strong>TEST SİPARİŞİ:</strong> Bu ekran yalnızca ödeme akışını denemek içindir. Gerçek havale/EFT göndermeyin. “Ödeme Yaptım” işlemi test bildirimi oluşturur.</p>
    @endif
    @if($order->status === 'pending_payment')
      <p class="payment-guide">Aşağıdaki banka hesaplarından birini seçin. Havale/EFT açıklamasına <strong>yalnızca sipariş numaranızı</strong> yazın ve <strong>toplam tutarı</strong> gönderin. Ödeme sonrası bildiriminizi iletin; banka kontrolü tamamlanana kadar sipariş ödeme bekliyor durumundadır.</p>
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
          @if($latestPaymentNotification?->status === 'rejected')
              <p class="payment-rejected"><strong>Önceki bildiriminiz doğrulanamadı.</strong>
                @if($latestPaymentNotification->admin_note) {{ $latestPaymentNotification->admin_note }} @endif
                Banka hareketini kontrol edip tekrar bildirebilirsiniz.</p>
            @endif
            <label class="checkout-field payment-note-label"><span>Ödeme yapan kişi / not (isteğe bağlı)</span>
              <input name="customer_note" maxlength="500" value="{{ old('customer_note') }}" placeholder="Örn. Havale farklı bir isimden yapıldı">
            </label>
            <button type="submit" class="checkout-action payment-notify-button">{{ $order->is_test ? 'Ödeme Yaptım (Test Bildirimi)' : 'Ödeme Yaptım – Bildir' }}</button>
            <small class="payment-verify-note">Bildiriminiz ödeme onayı değildir. Sipariş, banka hareketleri doğrulanınca hazırlanmaya alınır.</small>
        </form>
      @endif
    @elseif($latestPaymentNotification?->status === 'verified')
      <p class="payment-review-status is-verified"><x-store-icon name="check" /> Ödeme bildiriminiz yönetici tarafından doğrulandı. Siparişiniz hazırlanmaya alındı.</p>
    @elseif($order->status === 'cancelled')
      <p class="payment-rejected">Sipariş iptal edildi. Bu sipariş için artık ödeme bildirimi yapılamaz.</p>
    @else
      <p class="payment-review-status is-verified">Siparişinizin durumu: {{ $order->statusLabel() }}.</p>
    @endif
    <span class="payment-copy-live sr-only" role="status" aria-live="polite" data-payment-copy-status></span>
    <div class="payment-copy-toast" data-payment-copy-toast hidden aria-hidden="true"></div>
  </section>
  <div class="checkout-card">
    <h2>Sipariş detayları</h2>
    @foreach($order->items as $line)
      <div class="checkout-order-item"><span>{{ $line->product_name }} @if($line->option_value)<small>· {{ $line->option_label }}: {{ $line->option_value }}</small>@endif <small>× {{ $line->quantity }}</small></span>
        <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
    @endforeach
    <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
    <div class="checkout-price-line"><span>Kargo</span><strong>{{ $order->shipping_cents === 0 ? 'Ücretsiz' : number_format($order->shipping_cents / 100, 2, ',', '.').' ₺' }}</strong></div>
    <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
  </div>
  <div class="checkout-card"><h2>Teslimat bilgileri</h2>
    <p>{{ $order->customer_name }} · {{ $order->phone }}</p><p>{{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</p>
    @if($order->invoice_type === 'corporate')<p>Kurumsal fatura: {{ $order->company_title }}</p>@endif
    @if($order->order_note)<p><strong>Sipariş notu:</strong> {{ $order->order_note }}</p>@endif
    <div class="checkout-payment-option"><strong>Havale / EFT · {{ $order->statusLabel() }}</strong></div>
    <p class="checkout-muted">Havale/EFT ile ödeme bildirimleri banka hareketleri kontrol edilmeden onaylanmaz.</p>
  </div>
  <a class="checkout-action" href="{{ auth()->check() ? route('account.index') : route('home') }}">{{ auth()->check() ? 'Hesabıma Dön' : 'Alışverişe Dön' }}</a>
</main>
@endsection
