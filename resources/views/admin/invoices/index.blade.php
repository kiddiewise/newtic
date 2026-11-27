@extends('admin.layout')
@section('title','Fatura Yönetim Merkezi | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİPARİŞ YÖNETİMİ</p><h1>Fatura Yönetim Merkezi</h1><p>Fatura bekleyen siparişler ve EDM işlem kayıtları</p></div>
  <a class="admin-secondary" href="{{ route('admin.edm.edit') }}">EDM bağlantı ayarları</a>
</div>
<section class="admin-panel">
  <h2>Faturalandırma ayarları</h2>
  <form method="POST" action="{{ route('admin.invoices.settings') }}" class="admin-form">@csrf
    <input type="hidden" name="auto_invoice" value="0">
    <label class="admin-check"><input type="checkbox" name="auto_invoice" value="1" @checked($auto)> Sipariş tamamlandığında otomatik fatura oluştur</label>
    <p>Varsayılan manuel. Otomatik seçim etkinleştirildikten sonraki tamamlanma kayıtlarına uygulanır; önceki siparişleri topluca işlemez.</p>
    <label>Kargo KDV oranı<select name="shipping_vat_rate"><option value="">Belirlenmedi</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}" @selected((int)$shippingVat===$rate)>%{{ $rate }}</option>@endforeach</select></label>
    <button type="submit" class="admin-secondary">Ayarları kaydet</button>
  </form>
  @if(!$dispatchReady)<p role="status">EDM belge yükleme doğrulaması bekleniyor. Oluşturma istekleri saklanır; bağlantı doğrulanmadan gönderilmez.</p>@endif
</section>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>Fatura kesilmesi bekleyen siparişler ({{ $pending->total() }})</h2></div>
  <form method="POST" id="invoice-bulk" action="{{ route('admin.invoices.create') }}">@csrf</form>
  <div class="admin-form">
    <label>Kargo KDV oranı (gerekirse)<select name="shipping_rate" form="invoice-bulk"><option value="">Kayıtlı oranı kullan</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>
    <button class="admin-primary" type="submit" form="invoice-bulk" @disabled(!$canQueue||$pending->isEmpty())>Seçilen faturaları oluştur</button>
  </div>
  @forelse($pending as $order)
    <article style="padding:12px 0;border-bottom:1px solid var(--admin-border,#ddd)">
      <label class="admin-check"><input type="checkbox" name="orders[]" value="{{ $order->id }}" form="invoice-bulk"> {{ $order->order_number }} · {{ $order->statusLabel() }}</label>
      <div class="admin-detail-line"><a href="{{ route('admin.orders.show',$order) }}">Siparişi aç</a><strong>{{ number_format($order->total_cents/100,2,',','.') }} TL</strong></div>
      <form method="POST" action="{{ route('admin.invoices.create') }}" class="admin-form">@csrf
        <input type="hidden" name="orders[]" value="{{ $order->id }}">
        @if($order->shipping_cents>0)<label>Kargo KDV<select name="shipping_rate"><option value="">Kayıtlı oran</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>@endif
        <button type="submit" class="admin-secondary" @disabled(!$canQueue)>Fatura oluştur</button>
      </form>
    </article>
  @empty <p>Fatura bekleyen uygun sipariş yok.</p>@endforelse
  {{ $pending->links() }}
</section>
<section class="admin-panel" style="margin-top:16px">
  <h2>Fatura işlem kuyruğu</h2>
  @forelse($dispatches as $dispatch)
    <div class="admin-detail-line"><a href="{{ route('admin.orders.show',['order'=>$dispatch->order_id]) }}">{{ $dispatch->order_number }}</a><strong>{{ ['queued'=>'Sırada','processing'=>'İşleniyor','acknowledged'=>'EDM taslak onayı','blocked'=>'İşlem engellendi','uncertain'=>'Sonuç doğrulanamadı'][$dispatch->state] ?? 'Bilinmiyor' }}</strong></div>
    @if($dispatch->diagnostic)<p>{{ $dispatch->diagnostic }}</p>@endif
  @empty <p>Henüz fatura oluşturma isteği yok.</p>@endforelse
  <p>EDM taslak onayı nihai fatura düzenlendi anlamına gelmez. Belirsiz kayıtlar otomatik yeniden gönderilmez.</p>
</section>
@php($labels=['all'=>'Tüm hazırlıklar','local'=>'Yerel hazırlık','uncertain'=>'Sonuç belirsiz','observed'=>'EDM kaydı gözlemlendi'])
<div class="admin-dashboard-grid">
  @foreach($labels as $key=>$label)
    <section class="admin-panel">
      <p>{{ $label }}</p><h2>{{ $counts[$key] }}</h2>
      <a href="{{ route('admin.invoices.index',['state'=>$key]) }}" @if($filter===$key) aria-current="page" @endif>Listele</a>
    </section>
  @endforeach
</div>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>{{ $labels[$filter] }}</h2></div>
  @forelse($rows as $row)
    <article style="padding:16px 0;border-bottom:1px solid var(--admin-border,#ddd)">
      <div class="admin-detail-line"><span><a href="{{ route('admin.orders.invoice-preview',['order'=>$row->order_id]) }}">{{ $row->order_number }}</a></span><strong>{{ number_format($row->gross_cents/100,2,',','.') }} {{ $row->currency }}</strong></div>
      <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>ETTN</span><strong>{{ $row->uuid }}</strong></div>
      <div class="admin-detail-line"><span>Kayıt</span><strong>{{ $row->attempt_id === null ? 'Yerel teknik hazırlık' : (['outcome_unknown'=>'Sonuç belirsiz — tekrar gönderilmez','provider_record_found'=>'EDM kaydı gözlemlendi'][$row->state] ?? 'Bilinmiyor') }}</strong></div>
      @if($row->attempt_id !== null)
        <div class="admin-detail-line"><span>Senaryo</span><strong>{{ $row->profile }}</strong></div>
        <div class="admin-detail-line"><span>Yükleme yanıtı</span><strong>{{ ['draft_acknowledged'=>'Taslak yanıtı alındı','unconfirmed'=>'Doğrulanamadı'][$row->last_load_result ?? ''] ?? 'Henüz doğrulanmadı' }}</strong></div>
        <div class="admin-detail-line"><span>Fatura numarası</span><strong>{{ $row->observed_invoice_number ?? 'Doğrulanmadı' }}</strong></div>
        <div class="admin-detail-line"><span>Son sorgu</span><strong>{{ ['matched'=>'Eşleşti','unconfirmed'=>'Doğrulanamadı','identity_conflict'=>'Kimlik çelişkisi'][$row->last_lookup_state ?? ''] ?? 'Henüz yapılmadı' }}</strong></div>
        <div class="admin-detail-line"><span>GİB zarf kodu</span><strong>{{ $row->last_gib_code ?? 'Bildirilmedi' }}</strong></div>
        <div class="admin-detail-line"><span>Son sorgu zamanı</span><strong>{{ $row->last_lookup_at ?? '—' }}</strong></div>
        @if($row->can_reconcile)
          <form method="POST" action="{{ route('admin.invoices.reconcile',['attempt'=>$row->attempt_id]) }}">@csrf<button type="submit" class="admin-secondary">EDM durumunu güncelle</button></form>
        @else <p>Bu kayıt için mevcut EDM hesabıyla sorgulama yapılamıyor.</p> @endif
      @endif
    </article>
  @empty
    <p>Henüz bu filtreye uygun fatura hazırlığı yok. Sipariş detayındaki fatura önizleme ekranından yerel hazırlık oluşturabilirsiniz.</p>
  @endforelse
  {{ $rows->links() }}
</section>
@endsection
