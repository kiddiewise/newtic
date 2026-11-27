@extends('admin.layout')
@section('title', $order->order_number.' | NEWTIC')
@section('content')
<div class="newtic-order-detail">
  @if(in_array($order->status,\App\Services\Invoices\InvoiceDispatch::STATUSES,true))
    @php($invoiceRequest=\Illuminate\Support\Facades\DB::table('invoice_dispatch_requests')->where('order_id',$order->id)->first())
    <section class="admin-panel">
      <div class="admin-panel-title"><h2>Fatura işlemleri</h2></div>
      @if($invoiceRequest)<p>Fatura oluşturma isteği mevcut. <a href="{{ route('admin.invoices.index') }}">Fatura yönetimini aç</a></p>
      @elseif($paymentVerified && \App\Services\Invoices\InvoiceDispatch::staging())
        <form method="POST" action="{{ route('admin.invoices.create') }}" class="admin-form">@csrf
          <input type="hidden" name="orders[]" value="{{ $order->id }}">
          @if($order->shipping_cents>0)<label>Kargo KDV<select name="shipping_rate"><option value="">Kayıtlı oran</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>@endif
          <button type="submit" class="admin-primary">Fatura oluştur</button>
        </form>
      @else <a class="admin-secondary" href="{{ route('admin.invoices.index') }}">Fatura yönetimini aç</a>@endif
    </section>
  @endif
  <header class="newtic-detail-heading">
    <a class="admin-back" href="{{ route('admin.orders.index') }}">← Siparişler</a>
    <div class="newtic-detail-heading-row">
      <div><h1>{{ $order->order_number }}</h1><p>{{ $order->created_at->format('d.m.Y · H:i') }}</p></div>
      <details class="newtic-order-actions newtic-detail-documents">
        <summary>Belgeler <x-admin-icon name="more"/></summary>
        <div>
          <a href="{{ route('admin.invoices.index') }}"><x-admin-icon name="external-link"/> Fatura yönetimi</a>
          <a href="{{ route('admin.orders.invoice-preview', $order) }}"><x-admin-icon name="external-link"/> Fatura hesaplama ön izlemesi</a>
          <a href="{{ route('admin.orders.document', [$order, 'proforma']) }}"><x-admin-icon name="download"/> Promofatura PDF</a>
          <a href="{{ route('admin.orders.document', [$order, 'proforma', 'preview' => 1]) }}" target="_blank" rel="noopener noreferrer"><x-admin-icon name="external-link"/> Önizle / Yazdır</a>
        </div>
      </details>
    </div>
  </header>
  @php
    $processCancelled = $order->status === 'cancelled';
    $processCurrent = match($order->status) {'pending_payment'=>1,'preparing'=>2,'shipped','out_for_delivery'=>3,'completed'=>4,default=>null};
    $processStages = [
      ['label'=>'Sipariş oluşturuldu','short'=>'Sipariş','done'=>true],
      ['label'=>'Ödeme doğrulandı','short'=>'Ödeme','done'=>$paymentVerified],
      ['label'=>'Sipariş hazırlanıyor','short'=>'Hazırlık','done'=>in_array($order->status,['shipped','out_for_delivery','completed'],true)],
      ['label'=>'Kargoya verildi','short'=>'Kargo','done'=>$order->status === 'completed'],
      ['label'=>'Tamamlandı','short'=>'Teslimat','done'=>$order->status === 'completed'],
    ];
  @endphp
  <section class="admin-panel newtic-mobile-process" aria-label="Sipariş süreci" data-mobile-order-process>
    <div class="newtic-mobile-process-heading"><h2>Sipariş süreci</h2><span class="newtic-mobile-process-status {{ $processCancelled ? 'is-cancelled' : '' }}">{{ $order->statusLabel() }}</span></div>
    <ol class="newtic-mobile-process-track">
      @foreach($processStages as $index=>$stage)
        @php($current = !$processCancelled && !$stage['done'] && $processCurrent === $index)
        <li @class(['newtic-process-step','is-done'=>$stage['done'],'is-current'=>$current]) data-process-stage="{{ $index }}" data-process-state="{{ $stage['done'] ? 'done' : ($current ? 'current' : 'waiting') }}" @if($current) aria-current="step" @endif>
          <span class="newtic-process-node" aria-hidden="true">@if($stage['done'])<x-admin-icon name="check"/>@else{{ $index+1 }}@endif</span>
          <span class="newtic-process-label">{{ $stage['short'] }}</span>
          <span class="newtic-process-sr">{{ $stage['label'] }}: {{ $stage['done'] ? 'Tamamlandı' : ($current ? 'Devam ediyor' : ($processCancelled ? 'İptal nedeniyle ilerlemiyor' : 'Bekliyor')) }}</span>
        </li>
      @endforeach
    </ol>
  </section>
  <section class="newtic-detail-overview" aria-label="Sipariş özeti">
    <div class="newtic-detail-overview-top">
      <div><span>Sipariş tutarı</span><strong>{{ number_format($paymentBreakdown['order_total_cents']/100, 2, ',', '.') }} ₺</strong></div>
      <div class="newtic-detail-status"><span class="admin-state-pill state-{{ $order->status }}">{{ $order->statusLabel() }}</span>
</div>
    </div>
    <div class="newtic-detail-overview-meta">
      <div><span>Müşteri</span><strong>{{ $order->customer_name }}</strong></div>
      <div><span>Ödeme</span><strong>{{ $order->payment_method === 'paytr' ? 'PAYTR Kart' : 'Havale / EFT' }}</strong><small class="{{ $paymentVerified ? 'is-verified' : '' }}">{{ $paymentVerified ? 'Ödeme doğrulandı' : 'Ödeme bekleniyor' }}</small></div>
      <div><span>Ürün adedi</span><strong>{{ number_format($order->items->sum('quantity'),0,',','.') }}</strong></div>
    </div>
  </section>
  <nav class="newtic-detail-jumps" aria-label="Sipariş bölümleri">
    <a href="#order-manage">Durum</a><a href="#order-items">Ürünler</a><a href="#order-payment">Ödeme</a><a href="#order-customer">Müşteri</a><a href="#order-shipping">Kargo</a><a href="#order-returns">İptal / iade</a>
  </nav>
  <div class="newtic-detail-management">
    <section class="admin-panel admin-order-detail-actions" id="order-manage">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-orange"><x-admin-icon name="settings" /></span><div><h2>Durum yönetimi</h2></div></div>
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
      @if($order->shippingShipment)
        <p class="ship-info">Bu siparişin kargo aşaması Kargo Entegratör tarafından yönetiliyor. Değişiklikleri sağlayıcı panelinden yapıp aşağıdaki “Kargo durumunu yenile” işlemini kullan.</p>
      @endif
      @if($canCorrectFulfillment)
        <details class="ship-correction-panel newtic-order-advanced" @if($errors->has('reason') || $errors->has('status')) open @endif>
          <summary>Yanlış sipariş aşamasını düzelt</summary>
          <p>Hazırlık veya teslimat aşamasını geri ya da ileri alabilirsin. Bu işlem ödeme kaydını veya stoğu değiştirmez ve gerekçesi kaydedilir.</p>
          <form class="admin-form" method="POST" action="{{ route('admin.orders.correct', $order) }}">
            @csrf @method('PATCH')
            <label>Doğru sipariş aşaması
              <select name="status" required>
                @foreach(['preparing' => 'Hazırlanıyor', 'shipped' => 'Kargolandı', 'out_for_delivery' => 'Dağıtıma çıktı', 'completed' => 'Tamamlandı'] as $key => $label)
                  <option value="{{ $key }}" @selected($order->status === $key)>{{ $label }}</option>
                @endforeach
              </select>
            </label>
            <label>Düzeltme gerekçesi (zorunlu)
              <textarea name="reason" rows="2" required minlength="15" maxlength="500" placeholder="Hatalı durum seçildi, paket henüz kargoya teslim edilmedi..."></textarea>
            </label>
            <button class="admin-secondary" type="submit"><x-admin-icon name="check"/> Durumu gerekçeyle düzelt</button>
          </form>
        </details>
      @endif
      @if($canCorrectBankPayment)
        <details class="ship-correction-panel is-warning newtic-order-advanced" @if($errors->has('reason') || $errors->has('bank_not_received')) open @endif>
          <summary>Hatalı havale onayını geri al</summary>
          <p><strong>Yalnızca banka hesabına gerçekten para ulaşmadıysa kullan.</strong> Sipariş “Ödeme bekleniyor” durumuna döner; eski ödeme onayı geri çekilir ve işlem kaydedilir. Bankaya para geldiyse iade işlemi ayrıca yapılmalıdır.</p>
          <form class="admin-form" method="POST" action="{{ route('admin.orders.revoke-bank-verification', $order) }}">
            @csrf
            <label>Geri alma gerekçesi
              <textarea name="reason" rows="2" minlength="15" maxlength="500" required placeholder="Banka hareketi tekrar kontrol edildi; bu siparişe ait tahsilat bulunamadı..."></textarea>
            </label>
            <label class="ship-correction-confirm"><input type="checkbox" name="bank_not_received" value="1" required>
              Banka hesabına bu sipariş için ödeme ulaşmadığını kontrol ettim.
            </label>
            <button class="admin-danger" type="submit"><x-admin-icon name="alert"/> Hatalı ödeme onayını geri al</button>
          </form>
        </details>
      @elseif($order->payment_method === 'paytr')
        <p class="admin-description">PAYTR tarafından doğrulanan kart ödemesi yönetim panelinden geri alınmaz; bunun için ayrı iade akışı gerekir.</p>
      @endif
    </section>

  </div>
  <div class="admin-order-detail-grid">
    <div class="admin-order-detail-primary">
    <section class="admin-panel" id="order-items">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-blue"><x-admin-icon name="package" /></span><div><h2>Ürünler ve sipariş özeti</h2></div></div>
      @foreach($order->items as $line)
        <div class="admin-order-row newtic-order-item"><span><strong>{{ $line->product_name }}</strong><small>SKU: {{ $line->sku }} @if($line->option_value)· {{ $line->option_label }}: {{ $line->option_value }} @endif · {{ $line->quantity }} adet · Birim {{ number_format($line->unit_price_cents / 100, 2, ',', '.') }} ₺</small></span>
          <strong>{{ number_format($line->line_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
        @if(!empty($line->customer_photos))
          <div class="order-customer-photo-grid" aria-label="{{ $line->product_name }} için müşteri fotoğrafları">
            <p class="admin-description">Müşteri tarafından yüklenen fotoğraflar ({{ count($line->customer_photos) }})</p>
            @foreach($line->customer_photos as $index => $photo)
              <a href="{{ route('admin.orders.customer-photo', [$order, $line, $index]) }}"
                 target="_blank" rel="noopener noreferrer" title="{{ $photo['name'] ?? 'Müşteri fotoğrafı' }}">
                <img src="{{ route('admin.orders.customer-photo', [$order, $line, $index]) }}"
                   alt="{{ $line->product_name }} müşteri fotoğrafı {{ $index + 1 }}" width="112" height="112" loading="lazy">
                <small>Fotoğraf {{ $index + 1 }}</small>
              </a>
            @endforeach
          </div>
        @endif
      @endforeach
    </section>
    <section class="admin-panel" id="order-payment" aria-label="Ödeme toplamı">
      <div class="admin-panel-title"><h2>Ödeme toplamı</h2></div>
      <div class="checkout-price-line"><span>Ürünlerin toplamı</span><strong>{{ number_format($paymentBreakdown['products_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
      @if($paymentBreakdown['provider_difference_cents'] > 0)
        <div class="checkout-price-line"><span>Vade farkı / PAYTR ödeme farkı</span><strong>{{ number_format($paymentBreakdown['provider_difference_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
      @endif
      <div class="checkout-price-line"><span>Kargo</span><strong>{{ number_format($paymentBreakdown['shipping_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
      <div class="checkout-price-line"><span>Sipariş tutarı</span><strong>{{ number_format($paymentBreakdown['order_total_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
      @if($paymentBreakdown['payment_verified'])
        <div class="checkout-price-line is-total"><span>Ödendi (PAYTR / banka doğrulandı)</span><strong>{{ number_format($paymentBreakdown['paid_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
        @if($paymentBreakdown['provider_difference_cents'] > 0)
          <p class="admin-description">Fark, PayTR'nin doğrulanmış toplam tahsilatı ile sipariş tutarı arasındadır. Taksit/vade farkından kaynaklanabilir; ayrıntılı neden ve taksit sayısı bu bildirimle doğrulanmadığı için ayrıca belirtilmez.</p>
        @endif
      @else
        <div class="checkout-price-line is-total"><span>Ödeme durumu</span><strong>Ödeme bekleniyor</strong></div>
      @endif
    </section>
    <section class="admin-panel" id="order-customer">
      <div class="admin-editor-heading newtic-delivery-heading"><span class="admin-editor-heading-icon is-mint"><x-admin-icon name="users" /></span><div><h2>Müşteri ve teslimat</h2></div>
        @if($canEditDelivery)<button type="button" class="newtic-delivery-edit-link" data-delivery-edit-open aria-haspopup="dialog" aria-controls="delivery-editor">Düzenle</button>@endif
      </div>
      <div class="admin-detail-line"><span>Müşteri</span><strong>{{ $order->customer_name }}</strong></div>
      <div class="admin-detail-line"><span>E-posta</span><strong>{{ $order->customer_email }}</strong></div>
      <div class="admin-detail-line"><span>Telefon</span><strong>{{ $order->phone }}</strong></div>
      <div class="admin-detail-line"><span>Teslimat adresi</span><strong>{{ $order->street_address }}, {{ $order->district }}/{{ $order->city }}</strong></div>
      <div class="admin-detail-line"><span>Fatura</span><strong>{{ $order->invoice_type === 'corporate' ? 'Kurumsal' : 'Bireysel' }}</strong></div>
      @if($order->invoice_type === 'corporate')
        <div class="admin-detail-line"><span>Şirket</span><strong>{{ $order->company_title }}</strong></div>
        <div class="admin-detail-line"><span>Vergi</span><strong>{{ $order->tax_office }} · {{ $order->tax_number }}</strong></div>
      @endif
      @if($order->order_note)<div class="admin-detail-line"><span>Sipariş notu</span><strong>{{ $order->order_note }}</strong></div>@endif
      @if($deliveryEdit)<p class="newtic-delivery-edit-note">Son düzenleme: {{ \Illuminate\Support\Carbon::parse($deliveryEdit->created_at)->format('d.m.Y H:i') }}</p>@endif
      @if(!$canEditDelivery)<p class="newtic-delivery-edit-note">Kargo veya sipariş durumu nedeniyle teslimat bilgileri düzenlemeye kapalı.</p>@endif
      @if($errors->delivery->has('delivery'))<p role="alert" class="newtic-delivery-error">{{ $errors->delivery->first('delivery') }}</p>@endif
    </section>
    @if($order->payment_method === 'paytr')
      <section class="admin-panel">
        <div class="admin-panel-title"><h2>PAYTR kart ödeme geçmişi</h2></div>
        <p class="admin-description">PAYTR ödemesi imzalı bildirim veya doğrulanmış sunucu durum sorgusuyla onaylanır. Müşterinin ödeme ekranına dönmesi onay anlamına gelmez.</p>
        @forelse($order->paytrAttempts as $attempt)
          <article class="admin-payment-history">
            <span @class(['admin-state-pill', 'state-completed' => $attempt->status === 'success', 'state-cancelled' => $attempt->status === 'failed', 'state-pending_payment' => $attempt->status === 'pending'])>
              {{ match($attempt->status) { 'success' => 'Ödeme bildirimi doğrulandı', 'failed' => 'Başarısız', default => 'Bildirim bekleniyor' } }}
            </span>
            <small>PAYTR işlem no: {{ $attempt->merchant_oid }} · {{ $attempt->created_at->format('d.m.Y H:i') }}</small>
            @if($attempt->reconciliation_checked_at)
              <p class="admin-description">Son ödeme kontrolü: {{ $attempt->reconciliation_checked_at->format('d.m.Y H:i') }} · {{ $attempt->reconciliationLabel() }}</p>
            @endif
            @if($attempt->status === 'pending')
              @if(!$attempt->canRequestToken())<p class="admin-description">Ödeme ekranı kapalı. Kesin ödeme sonucu alınana kadar stok korunur. PayTR mağaza panelindeki İşlemler / Mağaza Bildirimleri bölümünden sonucu ve bildirim durumunu kontrol edin.</p>@endif
              <form method="POST" action="{{ route('admin.orders.paytr-reconcile',$order) }}">@csrf<button type="submit" class="admin-secondary">PayTR ödeme durumunu kontrol et</button></form>
            @endif
            <div class="admin-detail-line"><span>Başlangıç tutarı</span><strong>{{ number_format($attempt->amount_cents / 100, 2, ',', '.') }} ₺</strong></div>
            @if($attempt->status === 'success' && $attempt->paid_total_cents !== null)
              <div class="admin-detail-line"><span>PayTR doğrulanmış tahsilat</span><strong>{{ number_format($attempt->paid_total_cents / 100, 2, ',', '.') }} ₺</strong></div>
            @endif
            @if($attempt->failed_reason_code)
              <p class="admin-description">Hata kodu: {{ $attempt->failed_reason_code }} · {{ $attempt->failed_reason_message }}</p>
            @endif
          </article>
        @empty
          <p class="admin-empty">Henüz PAYTR ödeme girişimi yok.</p>
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
                    Siparişin ödeme kaydını kontrol ettim ve yönetim işlemini onaylıyorum. Bu işlem banka hareketi oluşturmaz.
                  @else
                    Tutarı ({{ number_format($order->total_cents/100, 2, ',', '.') }} ₺) ve açıklamadaki {{ $order->order_number }} numarasını banka hareketinde kontrol ettim.
                  @endif
                </label>
                <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Ödemeyi doğrula</button>
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
      @include('admin.returns.order-panel')
    </div>
    <div class="admin-order-detail-sidebar">
      <div id="order-shipping">
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

      </div>
    <section class="admin-panel newtic-desktop-process">
      <div class="admin-editor-heading"><span class="admin-editor-heading-icon is-purple"><x-admin-icon name="clock" /></span><div><h2>Sipariş süreci</h2></div></div>
      <div class="admin-detail-timeline">
        @foreach($processStages as $index => $stage)
          @php($current = !$processCancelled && !$stage['done'] && $processCurrent === $index)
          <div @class(['admin-detail-stage','is-done'=>$stage['done'],'is-now'=>$current]) @if($current) aria-current="step" @endif>
            <i>@if($stage['done'])✓@else{{ $index + 1 }}@endif</i>
            <span>{{ $stage['label'] }}<small>
              @if($index === 0){{ $order->created_at->format('d.m.Y H:i') }}
              @elseif($stage['done'])Tamamlandı
              @elseif($current){{ $index === 1 ? ($order->payment_method === 'paytr' ? 'PAYTR bildirimi bekleniyor' : 'Banka kontrolü bekleniyor') : $order->statusLabel() }}
              @elseif($processCancelled)İptal nedeniyle ilerlemiyor
              @else Henüz değil @endif
            </small></span>
          </div>
        @endforeach
      </div>
      @if($processCancelled)<span class="admin-state-pill state-cancelled">Sipariş iptal edildi</span>@endif
    </section>
    @if($order->statusChanges->isNotEmpty())
      <section class="admin-panel ship-order-audit">
        <details class="newtic-order-history"><summary>Sipariş durum değişiklikleri</summary>
        <ol>
          @foreach($order->statusChanges as $change)
            <li>
              <strong>{{ \App\Models\Order::labelForStatus($change->from_status) }} → {{ \App\Models\Order::labelForStatus($change->to_status) }}</strong>
              <small>{{ $change->created_at?->format('d.m.Y H:i') }} · {{ $change->user?->name ?: (str_starts_with($change->source,'paytr_') ? 'PayTR otomatik kontrol' : ($change->source === 'automatic_bank_expiry' ? 'Otomatik havale kontrolü' : 'Kargo Entegratör')) }} · {{ $change->source }}</small>
              @if($change->reason)<p>{{ $change->reason }}</p>@endif
            </li>
          @endforeach
        </ol></details>
      </section>
    @endif
    <section class="admin-panel">
      <details class="newtic-order-history"><summary>Bildirim ve e-posta geçmişi</summary>
      <p class="admin-description">Siparişe ilişkin müşteri ve yönetici e-postalarının gönderim durumu. SMTP sağlayıcısının teslimat garantisi değildir.</p>
      @forelse($emailDeliveries as $delivery)
        <div class="admin-detail-line">
          <span>{{ \App\Models\EmailSetting::EVENTS[$delivery->event] ?? $delivery->event }} · {{ $delivery->created_at?->format('d.m.Y H:i') }}</span>
          <strong>{{ ['sent'=>'Gönderildi','pending'=>'Sırada','processing'=>'İşleniyor','retry'=>'Tekrar denenecek','failed'=>'Başarısız','skipped'=>'Atlandı'][$delivery->status] ?? $delivery->status }}</strong>
        </div>
      @empty
        <p class="admin-description">Bu sipariş için henüz bir e-posta kaydı bulunmuyor.</p>
      @endforelse
      <a class="admin-secondary" href="{{ route('admin.email-center.index') }}">E-posta bildirim merkezine git</a>
    </details></section>
    <section class="admin-panel">
      <details class="newtic-order-history"><summary>Operasyon bilgileri</summary>
      <div class="admin-detail-line"><span>Ödeme yöntemi</span><strong>{{ $order->payment_method === 'paytr' ? 'PAYTR Kart' : 'Havale / EFT' }}</strong></div>
      <div class="admin-detail-line"><span>Ödeme durumu</span>
        <strong>{{ $paymentVerified ? 'Ödeme doğrulandı' : ($order->payment_method === 'paytr' ? 'PAYTR ödeme bildirimi bekleniyor / başarısız' : 'Havale/EFT doğrulaması bekleniyor') }}</strong>
      </div>
      <div class="admin-detail-line"><span>Sipariş durumu</span><strong>{{ $order->statusLabel() }}</strong></div>
      <div class="admin-detail-line"><span>Oluşturulma</span><strong>{{ $order->created_at->format('d.m.Y H:i') }}</strong></div>
      <div class="admin-detail-line"><span>Tür</span><strong>Sipariş</strong></div>
    </details></section>
    </div>
  </div>
</div>
@if($canEditDelivery)
  @include('admin.orders.delivery-editor')
@endif
@endsection
