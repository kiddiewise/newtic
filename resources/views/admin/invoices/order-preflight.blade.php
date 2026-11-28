@extends('admin.layout')
@section('title','Sipariş Fatura Ön Kontrolü | NEWTIC')
@section('content')
<div class="admin-page-heading"><div><p class="admin-eyebrow">FATURA YÖNETİMİ</p><h1>Belge türü ve e-Arşiv ön kontrolü</h1><p>{{ $orderNumber }}</p></div><a class="admin-secondary" href="{{ route('admin.invoices.index') }}">Fatura Merkezi</a></div>
<section class="admin-panel">
  <h2>{{ ['einvoice'=>'e-Fatura mükellefi','archive_candidate'=>'e-Arşiv adayı','archive_test_anonymous'=>'Sentetik bireysel e-Arşiv adayı','unknown'=>'Belge türü doğrulanamadı'][$result['type']] }}</h2>
  @if($result['metadata_saved']??false)<p role="status">Doğrulanan e-Arşiv bilgileri kaydedildi. Bu siparişin eski e-Fatura akışı kapatıldı.</p>@endif
  <p>Bu sonuç yalnızca test ortamında ön kontroldür. Fatura oluşturulmadı, numara ayrılmadı ve belge gönderilmedi.</p>
  <div class="admin-detail-line"><span>İnternet satış adresi</span><strong style="overflow-wrap:anywhere">{{ $result['website'] }}</strong></div>
  <div class="admin-detail-line"><span>Sipariş toplamı</span><strong>{{ $result['gross_cents']===null?'Doğrulanamadı':number_format($result['gross_cents']/100,2,',','.').' TL' }}</strong></div>
  <div class="admin-detail-line"><span>Ödeme yöntemi</span><strong>{{ ['paytr'=>'PAYTR','bank_transfer'=>'Havale / EFT'][$result['payment_method']]??'Doğrulanamadı' }}</strong></div>
  <div class="admin-detail-line"><span>Ödemenin sistemde doğrulandığı zaman</span><strong>{{ $result['payment_verified_at']??'Doğrulanamadı' }}</strong></div>
  <div class="admin-detail-line"><span>Kayıtlı kargo firması</span><strong>{{ $result['carrier_name']??'Kayıt yok' }}</strong></div>
  @php($fields=['website'=>'Mağaza adresi','buyer'=>'Mükellefiyet','series'=>'Bu yılın internet satış serisi','recipient_details'=>'Alıcı bilgileri','amounts'=>'Ürün, kargo ve KDV hesapları','payment_evidence'=>'Ödeme doğrulaması','actual_payment_date'=>'Gerçek ödeme tarihi','dispatch_date'=>'Gerçek sevk tarihi','carrier_identity'=>'Taşıyıcı VKN ve unvanı'])
  @php($states=['ok'=>'Doğrulandı','synthetic'=>'Yalnızca sentetik prova','missing'=>'Eksik','unverified'=>'Doğrulanamadı','requires_confirmation'=>'Belgeyle doğrulanmalı','operator_confirmed'=>'Yönetici onayıyla teknik kontrol tamamlandı','available'=>'Aktif seri bulundu','not_found'=>'Aktif seri bulunamadı','partial'=>'Seri kayıtları kısmen doğrulandı','not_applicable'=>'Uygulanmaz'])
  @foreach($result['checks'] as $key=>$state)
    <div class="admin-detail-line"><span>{{ $fields[$key]??$key }}</span><strong>{{ $states[$state]??'Doğrulanamadı' }}</strong></div>
  @endforeach
  @foreach($result['series'] as $series)<div class="admin-detail-line"><span>e-Arşiv seri adayı</span><strong>{{ $series }}</strong></div>@endforeach
  @if($result['internet_details_prepared'])<p role="status">İnternet satış alanları yerel teknik kontrolden geçti. Bu sonuç EDM kabulü değildir.</p>@endif
  <p>Ödeme doğrulama zamanı banka işlem tarihiyle aynı olmayabilir. Sevk tarihi veya taşıyıcı vergi bilgisi tahmin edilmez. e-Arşiv yüklemesi bu sürümde kapalıdır.</p>
</section>
@endsection
