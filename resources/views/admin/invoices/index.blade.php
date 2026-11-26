@extends('admin.layout')
@section('title','Fatura Yönetim Merkezi | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİPARİŞ YÖNETİMİ</p><h1>Fatura Yönetim Merkezi</h1><p>Yerel fatura hazırlıkları ve EDM test işlem kayıtları</p></div>
  <a class="admin-secondary" href="{{ route('admin.edm.edit') }}">EDM bağlantı ayarları</a>
</div>
<p>Bu merkez mali fatura arşivi değildir. Yerel teknik hazırlık ve EDM'den gözlemlenen kayıt ayrıdır. Fatura yükleme, gönderme ve tekrar gönderme kapalıdır.</p>
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
      <div class="admin-detail-line"><span><a href="{{ route('admin.orders.invoice-preview',['order'=>$row->order_id]) }}">{{ $row->order_number }}</a> · {{ $row->is_test ? 'Test siparişi' : 'Normal sipariş' }}</span><strong>{{ number_format($row->gross_cents/100,2,',','.') }} {{ $row->currency }}</strong></div>
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
        @else <p>Bu kayıt için mevcut test hesabıyla sorgulama yapılamıyor.</p> @endif
      @endif
    </article>
  @empty
    <p>Henüz bu filtreye uygun fatura hazırlığı yok. Sipariş detayındaki fatura önizleme ekranından yerel hazırlık oluşturabilirsiniz.</p>
  @endforelse
  {{ $rows->links() }}
</section>
@endsection
