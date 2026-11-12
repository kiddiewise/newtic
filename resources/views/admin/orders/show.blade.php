@extends('admin.layout')
@section('title', $order->order_number.' | NEWTIC')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <a class="admin-back" href="{{ route('admin.orders.index') }}">← Siparişler</a>
    <p class="admin-eyebrow">SİPARİŞ / DETAY VE OPERASYON</p>
    <h1>{{ $order->order_number }}</h1>
    <p>{{ $order->created_at->format('d.m.Y H:i') }}
      · <span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>
      @if($order->is_test)<span class="admin-test-tag">TEST SİPARİŞİ</span>@endif
    </p>
  </div>
  <a class="admin-secondary" href="{{ route('admin.orders.index') }}"><x-admin-icon name="arrow-right" /> Tüm siparişler</a>
</div>
<div class="admin-order-detail-grid">
  <div class="admin-order-detail-primary">
    <section class="admin-panel">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="users" /></span><div><h2>Müşteri ve teslimat</h2><p>Sipariş iletişim ve teslimat bilgileri</p></div></div>
      <div class="admin-detail-line"><span>Müşteri</span><strong>{{ $order->customer_name }}</strong></div>
      <div class="admin-detail-line"><span>E-posta</span><strong>{{ $order->customer_email }}</strong></div>
      <div class="admin-detail-line"><span>Telefon</span><strong>{{ $order->phone }}</strong></div>
      <div class="admin-detail-line"><span>Teslimat adresi</span><strong>{{ $order->address_label }} · {{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</strong></div>
      <div class="admin-detail-line"><span>Fatura</span><strong>{{ $order->invoice_type === 'corporate' ? 'Kurumsal' : 'Bireysel' }}</strong></div>
      @if($order->invoice_type === 'corporate')
        <div class="admin-detail-line"><span>Şirket</span><strong>{{ $order->company_title }}</strong></div>
        <div class="admin-detail-line"><span>Vergi</span><strong>{{ $order->tax_office }} · {{ $order->tax_number }}</strong></div>
      @endif
      @if($order->order_note)<div class="admin-detail-line"><span>Sipariş notu</span><strong>{{ $order->order_note }}</strong></div>@endif
    </section>
    <section class="admin-panel">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-blue"><x-admin-icon name="package" /></span><div><h2>Ürünler ve sipariş özeti</h2><p>Ürün ve toplam tutar değiştirilemez sipariş kaydından alınır.</p></div></div>
      @foreach($order->items as $line)
        <div class="admin-order-row"><span><strong>{{ $line->product_name }}</strong><small>SKU: {{ $line->sku }} @if($line->option_value)· {{ $line->option_label }}: {{ $line->option_value }} @endif · {{ $line->quantity }} adet · Birim {{ number_format($line->unit_price_cents / 100, 2, ',', '.') }} ₺</small></span>
          <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
      @endforeach
      <div class="checkout-price-line"><span>Ara toplam</span><strong>{{ number_format($order->subtotal_cents / 100, 2, ',', '.') }} ₺</strong></div>
      <div class="checkout-price-line"><span>Kargo</span><strong>{{ number_format($order->shipping_cents / 100, 2, ',', '.') }} ₺</strong></div>
      <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($order->total_cents / 100, 2, ',', '.') }} ₺</strong></div>
    </section>
    @if($order->payment_method === 'paytr')
      <section class="admin-panel">
        <div class="admin-panel-title"><h2>PAYTR kart ödeme geçmişi</h2></div>
        <p class="admin-description">PAYTR test işlemi yalnızca doğrulanmış sunucu bildirimiyle sonuçlandırılır. Müşterinin ödeme ekranına dönmesi onay anlamına gelmez.</p>
        @forelse($order->paytrAttempts as $attempt)
          <article class="admin-payment-history">
            <span @class(['admin-state-pill', 'state-completed' => $attempt->status === 'success', 'state-cancelled' => $attempt->status === 'failed', 'state-pending_payment' => $attempt->status === 'pending'])>
              {{ match($attempt->status) { 'success' => 'Test ödemesi doğrulandı', 'failed' => 'Başarısız', default => 'Bildirim bekleniyor' } }}
            </span>
            <small>PAYTR işlem no: {{ $attempt->merchant_oid }} · {{ $attempt->created_at->format('d.m.Y H:i') }}</small>
            <div class="admin-detail-line"><span>İşlem tutarı</span><strong>{{ number_format($attempt->amount_cents / 100, 2, ',', '.') }} ₺</strong></div>
            @if($attempt->failed_reason_code)
              <p class="admin-description">Hata kodu: {{ $attempt->failed_reason_code }} · {{ $attempt->failed_reason_message }}</p>
            @endif
          </article>
        @empty
          <p class="admin-empty">Henüz PAYTR test ödeme girişimi yok.</p>
        @endforelse
      </section>
    @else
    <section class="admin-panel">
      <div class="admin-panel-title"><h2>Havale / EFT ödeme kontrolü</h2>
        <a class="admin-text-link" href="{{ route('admin.payment-notifications.index') }}">Bildirim kutusu <x-admin-icon name="arrow-right" /></a>
      </div>
      <p class="admin-description">Ödeme bildirimi, para transferinin gerçekleştiğine dair kanıt değildir. Banka hareketlerinde sipariş numarasını, tutarı ve alıcı hesabı mutlaka kontrol edin.</p>
      @forelse($order->paymentNotifications as $notification)
        <article class="admin-payment-history">
          <span class="admin-state-pill {{ $notification->status === 'verified' ? 'state-completed' : ($notification->status === 'rejected' ? 'state-cancelled' : 'state-pending_payment') }}">{{ $notification->statusLabel() }}</span>
          <small>{{ $notification->created_at->format('d.m.Y H:i') }} · {{ $notification->bank_name_snapshot }} · {{ $notification->iban_snapshot }}</small>
          @if($notification->customer_note)<p><strong>Müşteri notu:</strong> {{ $notification->customer_note }}</p>@endif
          @if($notification->reviewer)<small><strong>İnceleyen:</strong> {{ $notification->reviewer->name }} · {{ $notification->reviewed_at?->format('d.m.Y H:i') }}</small>@endif
          @if($notification->admin_note)<p><strong>Kontrol notu:</strong> {{ $notification->admin_note }}</p>@endif
          @if($notification->status === 'pending' && $order->status === 'pending_payment')
            <div class="admin-payment-actions">
              <form class="admin-form" method="POST" action="{{ route('admin.payment-notifications.verify', $notification) }}">
                @csrf
                <label class="admin-check"><input type="checkbox" name="bank_verified" value="1" required>
                  @if($order->is_test)
                    Gerçek banka transferi yapılmadığını biliyorum. {{ $order->order_number }} numaralı test bildirimini yalnızca deneme amacıyla onaylıyorum.
                  @else
                    Tutarı ({{ number_format($order->total_cents/100, 2, ',', '.') }} ₺) ve açıklamadaki {{ $order->order_number }} numarasını banka hareketinde kontrol ettim.
                  @endif
                </label>
                <button class="admin-primary" type="submit"><x-admin-icon name="check" /> {{ $order->is_test ? 'TEST Ödemeyi doğrula' : 'Ödemeyi doğrula' }}</button>
              </form>
              <form class="admin-form" method="POST" action="{{ route('admin.payment-notifications.reject', $notification) }}">
                @csrf
                <label>Red gerekçesi<textarea name="admin_note" required minlength="8" maxlength="500" rows="2" placeholder="Banka hareketinde ödeme bulunamadı..."></textarea></label>
                <button class="admin-danger" type="submit">Bildirimi reddet</button>
              </form>
            </div>
          @endif
        </article>
      @empty
        <p class="admin-empty">Henüz ödeme bildirimi yapılmadı. Sipariş ödeme bekliyor durumunda kalır.</p>
      @endforelse
    </section>
    @endif
  </div>
  <div class="admin-order-detail-sidebar">
    <section class="admin-panel">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-purple"><x-admin-icon name="clock" /></span><div><h2>Sipariş süreci</h2><p>Siparişin hangi aşamada olduğunu takip et</p></div></div>
      @php
        $step = match($order->status){'preparing'=>2,'shipped'=>3,'out_for_delivery'=>3,'completed'=>4,default=>0};
        $timeline = ['Sipariş oluşturuldu','Ödeme doğrulandı','Sipariş hazırlanıyor','Kargoya verildi','Tamamlandı'];
      @endphp
      <div class="admin-detail-timeline">
        @foreach($timeline as $index => $label)
          <div @class(['admin-detail-stage','is-done' => $step >= $index && $order->status !== 'cancelled','is-now' => $index === $step && $order->status !== 'cancelled'])>
            <i>@if($step >= $index && $order->status !== 'cancelled')✓@else{{ $index + 1 }}@endif</i>
            <span>{{ $label }}<small>
              @if($index === 0){{ $order->created_at->format('d.m.Y H:i') }}
              @elseif($index === 1 && $step === 0){{ $order->payment_method === 'paytr' ? 'PAYTR test bildirimi bekleniyor' : 'Banka kontrolü bekleniyor' }}
              @elseif($index <= $step)Tamamlandı
              @else Henüz değil @endif
            </small></span>
          </div>
        @endforeach
      </div>
      @if($order->status === 'cancelled')<span class="admin-state-pill state-cancelled">Sipariş iptal edildi</span>@endif
    </section>
    <section class="admin-panel admin-order-detail-actions">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-orange"><x-admin-icon name="settings" /></span><div><h2>Durum yönetimi</h2><p>Bir sonraki sipariş adımını seç</p></div></div>
      @if($allowed)
        <form class="admin-form" method="POST" action="{{ route('admin.orders.update', $order) }}">
          @csrf @method('PATCH')
          <label>Yeni durum
            <select name="status" required>
              @foreach($allowed as $state)
                <option value="{{ $state }}">{{ match($state) {
                  'preparing' => 'Hazırlanıyor', 'shipped' => 'Kargolandı',
                  'out_for_delivery' => 'Dağıtıma çıktı', 'completed' => 'Tamamlandı', 'cancelled' => 'İptal edildi', default => $state
                } }}</option>
              @endforeach
            </select>
          </label>
          <button type="submit" class="admin-primary"><x-admin-icon name="check" /> Durumu güncelle</button>
        </form>
      @else
        <p class="admin-description">Sipariş bu durumdan sonra değiştirilemez.</p>
      @endif
      <p class="admin-description">Ödeme onaylanmadan “Hazırlanıyor” seçilemez. Onaylanmış ödeme iade kaydı olmadan iptal edilemez.</p>
    </section>
    @include('admin.orders.shipping-integration')
    @if(in_array($order->status, ['shipped', 'out_for_delivery', 'completed'], true) && !$order->shippingShipment)
    <section class="admin-panel admin-shipping-editor">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-blue"><x-admin-icon name="truck" /></span>
        <div><h2>Kargo ve takip bilgileri</h2><p>Bu bilgiler müşterinin sipariş takip ekranında görüntülenir.</p></div>
      </div>
      <form class="admin-form admin-shipping-fields" method="POST" action="{{ route('admin.orders.shipping', $order) }}">
        @csrf @method('PATCH')
        <label>Kargo firması
          <input name="carrier_name" maxlength="120" value="{{ old('carrier_name', $order->carrier_name) }}" placeholder="Örn. Yurtiçi Kargo">
        </label>
        <label>Kargo takip numarası
          <input name="tracking_number" maxlength="120" value="{{ old('tracking_number', $order->tracking_number) }}" placeholder="Örn. 123456789">
        </label>
        <label>Resmi takip bağlantısı (isteğe bağlı)
          <input type="url" name="tracking_url" maxlength="2000" value="{{ old('tracking_url', $order->tracking_url) }}" placeholder="https://..." pattern="https://.*">
        </label>
        <p class="admin-description">Bağlantı eklenmediyse müşteri yalnızca kaydedilmiş firma ve takip numarasını görür; çalışmayan kargo linki gösterilmez.</p>
        <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Kargo bilgilerini kaydet</button>
      </form>
    </section>
    @endif
    <section class="admin-panel">
      <h2>Operasyon bilgileri</h2>
      <div class="admin-detail-line"><span>Ödeme yöntemi</span><strong>{{ $order->payment_method === 'paytr' ? 'PAYTR Kart (Test)' : 'Havale / EFT' }}</strong></div>
      <div class="admin-detail-line"><span>Ödeme durumu</span><strong>{{ $order->status === 'pending_payment' ? ($order->payment_method === 'paytr' ? 'PAYTR bildirim bekleniyor' : 'Banka kontrolü bekleniyor') : $order->statusLabel() }}</strong></div>
      <div class="admin-detail-line"><span>Oluşturulma</span><strong>{{ $order->created_at->format('d.m.Y H:i') }}</strong></div>
      <div class="admin-detail-line"><span>Tür</span><strong>{{ $order->is_test ? 'TEST siparişi' : 'Gerçek sipariş' }}</strong></div>
    </section>
  </div>
</div>
@endsection