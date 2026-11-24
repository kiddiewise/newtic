@php
  $returnCases = $order->returnRequests()->get();
  $openReturn = $returnCases->first(fn($case) => in_array($case->status, \App\Models\OrderReturnRequest::ACTIVE, true));
  $usedReturn = [];
  foreach($returnCases->where('status','completed') as $case) {
      foreach($case->items as $line) $usedReturn[$line['id']] = ($usedReturn[$line['id']] ?? 0) + $line['quantity'];
  }
  $canRequestReturn = $order->status !== 'cancelled' && !$openReturn && $order->items->contains(fn($line) => $line->quantity > ($usedReturn[$line->id] ?? 0));
  $returnAction = \Illuminate\Support\Facades\URL::temporarySignedRoute('checkout.returns.store', now()->addDays(14), ['order'=>$order->id], absolute:false);
@endphp
@if($returnCases->isNotEmpty() || $canRequestReturn)
<section class="checkout-card" aria-labelledby="return-title">
  <h2 id="return-title">İptal ve iade</h2>
  @foreach($returnCases as $case)
    <article class="payment-copy-item" style="margin-block:10px">
      <strong>{{ $case->label() }}</strong>
      <small>{{ $case->reference }} · {{ $case->created_at->format('d.m.Y H:i') }}</small>
      @foreach($case->items as $line)<span>{{ $line['name'] }} {{ $line['option'] ?? '' }} · {{ $line['quantity'] }} adet</span>@endforeach
      @if($case->return_code)<span>{{ $case->return_carrier }} iade kodu</span><div class="payment-copy-line"><strong>{{ $case->return_code }}</strong><button type="button" class="payment-copy-button" data-copy-value="{{ $case->return_code }}" data-copy-label="İade kodu" aria-label="İade kodunu kopyala"><x-store-icon name="copy" /></button></div>@endif
      @if($case->public_note)<p>{{ $case->public_note }}</p>@endif
      @if($case->refund_status === 'verified')<p>Para iadesi doğrulandı: {{ number_format($case->refund_cents/100,2,',','.') }} ₺ (sipariş bedeli)</p>
      @elseif(in_array($case->refund_status,['processing','submitted','uncertain'],true))<p>Para iadesi kontrol ediliyor. Henüz kesinleşmedi.</p>@endif
    </article>
  @endforeach
  @if($canRequestReturn)
    <details>
      <summary class="checkout-outline-action" style="cursor:pointer">İptal / iade talebi oluştur</summary>
      <form method="POST" action="{{ $returnAction }}" style="display:grid;gap:12px;margin-top:12px">
        @csrf
        <label class="checkout-field"><span>Talep türü</span><select name="kind"><option value="cancel" @selected(in_array($order->status,['pending_payment','preparing'],true))>Siparişi iptal et</option><option value="return" @selected(!in_array($order->status,['pending_payment','preparing'],true))>Ürün iadesi</option></select></label>
        @foreach($order->items as $line)
          @php($remaining = max(0,$line->quantity-($usedReturn[$line->id] ?? 0)))
          @if($remaining)
          <label class="checkout-field"><span>{{ $line->product_name }} {{ $line->option_value }} · {{ $remaining }} adet</span><input type="number" inputmode="numeric" name="items[{{ $line->id }}]" min="0" max="{{ $remaining }}" value="{{ in_array($order->status,['pending_payment','preparing'],true) ? $remaining : 0 }}" required></label>
          @endif
        @endforeach
        <label class="checkout-field"><span>Neden</span><select name="reason">@foreach(\App\Models\OrderReturnRequest::REASONS as $key=>$label)<option value="{{ $key }}">{{ $label }}</option>@endforeach</select></label>
        <label class="checkout-field"><span>Açıklama (isteğe bağlı)</span><textarea name="note" maxlength="1000" rows="3"></textarea></label>
        <p class="payment-guide">Talebiniz mağazaya iletilir. Cayma ve ayıplı mal haklarınız saklıdır. Para iadesi ayrıca doğrulanır.</p>
        <button class="checkout-action" type="submit">Talebi gönder</button>
      </form>
    </details>
  @endif
</section>
@endif
