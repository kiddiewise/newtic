@extends('layouts.store')
@section('title','Kampanya E-posta Tercihleri')
@section('content')
<main class="checkout-shell" id="content">
 <div class="checkout-card" style="max-width:550px;margin:40px auto;text-align:center">
  <h1>Kampanya e-posta tercihleri</h1>
  @if($contact->unsubscribed_at)
   <p>Bu adrese kampanya e-postası gönderimi durdurulmuştur. Sipariş ve hesap güvenliği bildirimleri etkilenmez.</p>
  @else
   <p>Kampanya ve tanıtım e-postalarından ücretsiz olarak çıkabilirsiniz. Sipariş ve kargo bildirimleriniz gelmeye devam eder.</p>
   <form method="POST" action="{{ route('marketing.unsubscribe',$contact->unsubscribe_token) }}">
    @csrf
    <button class="checkout-action" type="submit">Kampanya e-postalarını durdur</button>
   </form>
  @endif
  <a href="{{ route('home') }}" class="checkout-outline-action" style="display:inline-block;margin-top:18px">Mağazaya dön</a>
 </div>
</main>
@endsection
