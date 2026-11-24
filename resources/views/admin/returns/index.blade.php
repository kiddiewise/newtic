@extends('admin.layout')
@section('title','İptal ve İade Talepleri | NEWTIC')
@section('content')
<div class="admin-page-heading"><p class="admin-eyebrow">SİPARİŞ / SATIŞ SONRASI</p><h1>İptal ve iade talepleri</h1><p>Talepleri, kargo geri dönüşünü ve para iadesini tek yerden takip edin.</p></div>
<div class="admin-panel">
  <nav class="admin-order-filters">
    <a href="{{ route('admin.returns.index') }}" @class(['is-active'=>$status===''])>Tümü</a>
    <a href="{{ route('admin.returns.index',['status'=>'open']) }}" @class(['is-active'=>$status==='open'])>Açık talepler</a>
    @foreach(\App\Models\OrderReturnRequest::LABELS as $key=>$label)<a href="{{ route('admin.returns.index',['status'=>$key]) }}" @class(['is-active'=>$status===$key])>{{ $label }}</a>@endforeach
  </nav>
  <div class="admin-order-card-list">
    @forelse($cases as $case)
      <a class="admin-order-list-item" href="{{ route('admin.orders.show',$case->order_id) }}#order-returns"><span><strong>{{ $case->order->order_number }}</strong><small>{{ $case->reference }} · {{ $case->order->customer_name }}</small></span><span>{{ $case->kind==='cancel'?'İptal':'İade' }}</span><span>{{ $case->label() }}</span><span>{{ $case->created_at->format('d.m.Y H:i') }}</span></a>
    @empty<p class="admin-empty">Bu filtrede talep bulunmuyor.</p>@endforelse
  </div>
  <div class="pagination-wrap">{{ $cases->links() }}</div>
</div>
@endsection
