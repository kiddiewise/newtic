<section class="admin-panel" id="order-returns">
  <div class="admin-panel-title"><h2>İptal ve iade</h2><a class="admin-text-link" href="{{ route('admin.returns.index') }}">Tüm talepler</a></div>
  @php
    $cases = $order->returnRequests()->get();
    $used = [];
    foreach($cases->where('status','completed') as $previous) foreach($previous->items as $line) $used[$line['id']] = ($used[$line['id']] ?? 0) + $line['quantity'];
  @endphp
  @if($order->status !== 'cancelled' && !$cases->contains(fn($c)=>in_array($c->status,\App\Models\OrderReturnRequest::ACTIVE,true)) && $order->items->contains(fn($line)=>$line->quantity > ($used[$line->id] ?? 0)))
    <details style="margin-bottom:12px"><summary class="admin-secondary">Yeni iptal / iade talebi</summary>
      <form method="POST" action="{{ route('admin.returns.store',$order) }}" class="admin-form">@csrf
        <label>Talep türü<select name="kind"><option value="cancel" @selected(in_array($order->status,['pending_payment','preparing'],true))>İptal</option><option value="return" @selected(!in_array($order->status,['pending_payment','preparing'],true))>İade</option></select></label>
        @foreach($order->items as $line)
          @php($remaining=max(0,$line->quantity-($used[$line->id] ?? 0)))
          @if($remaining)<label>{{ $line->product_name }} {{ $line->option_value }} ({{ $remaining }} adet)<input name="items[{{ $line->id }}]" type="number" min="0" max="{{ $remaining }}" value="{{ in_array($order->status,['pending_payment','preparing'],true)?$remaining:0 }}" required></label>@endif
        @endforeach
        <label>Neden<select name="reason">@foreach(\App\Models\OrderReturnRequest::REASONS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        <label>Not<textarea name="note" rows="2" maxlength="1000"></textarea></label>
        <button class="admin-primary">Talep oluştur</button>
      </form>
    </details>
  @endif
  @php($confirmedRefund = $cases->where('refund_status','verified')->sum('refund_cents'))
  @if($confirmedRefund)<div class="admin-detail-line"><span>Doğrulanan ana bedel iadeleri</span><strong>{{ number_format($confirmedRefund/100,2,',','.') }} ₺</strong></div><div class="admin-detail-line"><span>İade sonrası kalan sipariş bedeli</span><strong>{{ number_format(max(0,$order->total_cents-$confirmedRefund)/100,2,',','.') }} ₺</strong></div>@endif
  @forelse($cases as $case)
    <article class="admin-payment-history">
      <strong>{{ $case->kind==='cancel'?'İptal':'İade' }} · {{ $case->label() }}</strong>
      <small>{{ $case->reference }} · {{ $case->created_at->format('d.m.Y H:i') }}</small>
      @if(in_array($case->status,\App\Models\OrderReturnRequest::ACTIVE,true))<p class="admin-description">İç takip hedefi: {{ $case->created_at->copy()->addDays(14)->format('d.m.Y') }} · Yasal süreyi teslimat ve cayma bildirimine göre ayrıca kontrol edin.</p>@endif
      <p>{{ \App\Models\OrderReturnRequest::REASONS[$case->reason] ?? $case->reason }} — {{ $case->customer_note }}</p>
      @foreach($case->items as $line)<div class="admin-detail-line"><span>{{ $line['name'] }} {{ $line['option'] ?? '' }} × {{ $line['quantity'] }}</span><strong>{{ number_format($line['cents']/100,2,',','.') }} ₺</strong></div>@endforeach
      <div class="admin-detail-line"><span>İstenen ürün bedeli</span><strong>{{ number_format($case->items_cents/100,2,',','.') }} ₺</strong></div>
      @if($case->return_code)<p>İade kodu: {{ $case->return_carrier }} · {{ $case->return_code }}</p>@endif
      @if($case->public_note)<p>Müşteriye not: {{ $case->public_note }}</p>@endif
      @if($case->refund_cents)<div class="admin-detail-line"><span>İade ana tutarı</span><strong>{{ number_format($case->refund_cents/100,2,',','.') }} ₺</strong></div>@endif
      @if($case->refund_status === 'verified')<p>Para iadesi doğrulandı. Stok ve teslimat ayrı kontrol edilir.</p>
      @elseif(in_array($case->refund_status,['processing','submitted','uncertain'],true))<p>İade sonucu bekleniyor. Tekrar para göndermeyin; önce sağlayıcıyı sorgulayın.</p>@endif
      @if(in_array($case->status,\App\Models\OrderReturnRequest::ACTIVE,true))
        <form method="POST" action="{{ route('admin.returns.update',$case) }}" class="admin-form" style="margin-top:12px">
          @csrf
          @if($case->refund_status==='none')
            @if($case->status==='requested')
              <label>Müşteriye not (ret için zorunlu)<textarea name="public_note" rows="2" maxlength="1000"></textarea></label>
              <div class="admin-form-actions"><button class="admin-primary" name="action" value="approve">Talebi onayla</button><button class="admin-secondary" name="action" value="reject">Gerekçeyle sonuçlandır</button></div>
            @else
              @if(in_array($case->status,['approved','awaiting_return'],true))
                <label>İade taşıyıcısı<input name="return_carrier" maxlength="100" value="{{ $case->return_carrier }}"></label>
                <label>Sağlayıcıdan alınan iade kodu<input name="return_code" maxlength="100" value="{{ $case->return_code }}"></label>
                <small>Şu an kod taşıyıcı panelinden alınır; bu alan kargo oluşturmaz.</small>
                <div class="admin-form-actions"><button class="admin-secondary" name="action" value="return_code">İade kodunu müşteriye ilet</button><button class="admin-secondary" name="action" value="received">Ürün teslim alındı</button></div>
              @endif
              <label><input type="checkbox" name="shipping" value="1"> İlk kargo bedelini iade et (tam caymada otomatik)</label>
              <p>Ürün bedeli: {{ number_format($case->items_cents/100,2,',','.') }} ₺. İlk kargo dahil üst tutar: {{ number_format(($case->items_cents + max(0,$order->shipping_cents-$cases->whereIn('refund_status',['processing','uncertain','submitted','verified'])->sum('shipping_cents')))/100,2,',','.') }} ₺. Vade farkı ayrıca eklenmez.</p>
              @if($order->payment_method==='bank_transfer')<label>Banka iade işlem referansı (gerçek transfer sonrası)<input name="bank_reference" maxlength="100"></label>@endif
              <label><input type="checkbox" name="confirmed" value="1"> Ödeme / kargo ve iade tutarını kontrol ettim; işlemi onaylıyorum.</label>
              <div class="admin-form-actions">
                @if($paymentBreakdown['payment_verified'])
                <button class="admin-primary" name="action" value="refund" onclick="return confirm('Para iadesi geri alınamaz. Devam edilsin mi?')">{{ $order->payment_method==='paytr'?'PAYTR iadesini başlat':'Banka iadesini kaydet' }}</button>
                @elseif($order->status==='cancelled' && $case->refund_status==='none')
                <button class="admin-secondary" name="action" value="complete_unpaid">Mevcut iptali talebe işle (stok değişmez)</button>
                @elseif($order->payment_method==='bank_transfer' && $order->status==='pending_payment')
                <button class="admin-primary" name="action" value="complete_unpaid" onclick="return confirm('Banka hareketlerinde ödeme olmadığını doğruladınız mı?')">Ödenmemiş siparişi iptal et</button>
                @endif
              </div>
            @endif
          @elseif(in_array($case->refund_status,['processing','submitted','uncertain'],true) && $order->payment_method==='paytr')
            @if(!$case->received_at)<button class="admin-secondary" name="action" value="received">Ürün teslim alındı</button>@endif
            <button class="admin-primary" name="action" value="reconcile">PayTR iade durumunu sorgula</button>
          @elseif($case->refund_status==='verified')
            @if(!$case->received_at)<button class="admin-secondary" name="action" value="received">Ürün teslim alındı</button>@endif
            <label><input type="checkbox" name="restock" value="1"> Teslim alınmış veya gönderilmemiş satılabilir ürünleri stoğa ekle</label>
            <button class="admin-primary" name="action" value="finish">Talebi tamamla</button>
          @endif
        </form>
      @endif
    </article>
  @empty
    <p class="admin-empty">Bu sipariş için henüz iptal veya iade talebi yok.</p>
  @endforelse
</section>
