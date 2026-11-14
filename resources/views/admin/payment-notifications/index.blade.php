@extends('admin.layout')
@section('title', 'Ödeme Bildirimleri | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİPARİŞ / ÖDEME KONTROLÜ</p><h1>Ödeme bildirimleri</h1>
    <p>Müşterinin “Ödeme Yaptım” bildirimi ödeme kanıtı değildir. Banka hareketlerini ve sipariş numarasını kontrol et.</p></div>
</div>
<div class="admin-panel">
  <div class="admin-order-filters">
    @foreach(['pending'=>'Kontrol bekleyen', 'verified'=>'Doğrulanan', 'rejected'=>'Reddedilen', 'all'=>'Tümü'] as $value => $label)
      <a @class(['is-active' => $status === $value]) href="{{ route('admin.payment-notifications.index', ['status' => $value]) }}">{{ $label }} @if($value === 'pending')({{ $pendingCount }})@endif</a>
    @endforeach
  </div>
  @forelse($notifications as $notification)
    <article class="admin-payment-record">
      <div class="admin-payment-record-head">
        <div><a href="{{ route('admin.orders.show', $notification->order) }}"><strong>{{ $notification->order->order_number }}</strong></a>
          <small>{{ $notification->order->customer_name }} · {{ $notification->created_at->format('d.m.Y H:i') }} @if($notification->order->is_test) · TEST @endif</small>
        </div>
        <strong>{{ number_format($notification->order->total_cents / 100, 2, ',', '.') }} ₺</strong>
      </div>
      <p>{{ $notification->bank_name_snapshot }} · <span class="bank-mono">{{ $notification->iban_snapshot }}</span></p>
      <p><strong>{{ $notification->statusLabel() }}</strong></p>
      @if($notification->customer_note)<p>Gönderenin notu: {{ $notification->customer_note }}</p>@endif
      @if($notification->admin_note)<p>Kontrol notu: {{ $notification->admin_note }}</p>@endif
      @if($notification->status === 'pending')
        <div class="admin-payment-actions">
          <form action="{{ route('admin.payment-notifications.verify', $notification) }}" method="POST" class="admin-form">
            @csrf
            <label class="admin-check"><input name="bank_verified" type="checkbox" value="1" required>
              @if($notification->order->is_test)
                Gerçek banka transferi yapılmadığını biliyorum. <strong>{{ $notification->order->order_number }}</strong> numaralı test bildirimini yalnızca deneme amacıyla onaylıyorum.
              @else
                Banka hareketinde <strong>{{ number_format($notification->order->total_cents / 100, 2, ',', '.') }} ₺</strong> tutarını ve <strong>{{ $notification->order->order_number }}</strong> açıklamasını kontrol ettim.
              @endif
            </label>
            <button type="submit" class="admin-primary">{{ $notification->order->is_test ? 'TEST Ödemeyi Doğrula' : 'Ödemeyi Doğrula' }} / Hazırlanıyor</button>
          </form>
          <form action="{{ route('admin.payment-notifications.reject', $notification) }}" method="POST" class="admin-form">
            @csrf
            <label>Reddetme nedeni <textarea name="admin_note" rows="2" minlength="8" maxlength="500" required placeholder="Örn. Banka hareketlerinde ödeme bulunamadı."></textarea></label>
            <button type="submit" class="admin-danger">Bildirimi reddet</button>
          </form>
        </div>
      @endif
    </article>
  @empty
    <p class="admin-empty">Bu filtrede ödeme bildirimi bulunmuyor.</p>
  @endforelse
  {{ $notifications->links() }}
</div>
@endsection
