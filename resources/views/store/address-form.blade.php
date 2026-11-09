@extends('layouts.store')
@section('title', ($address->exists ? 'Adresi Düzenle' : 'Yeni Adres').' | Polaris Kurşun')
@section('content')
<main class="checkout-shell" id="content">
  <div class="checkout-top"><a href="{{ route('account.addresses.index') }}" aria-label="Geri dön">‹</a><strong>{{ $address->exists ? 'Adresi Düzenle' : 'Yeni Adres' }}</strong><span></span></div>
  <form class="checkout-card checkout-form" method="POST" action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}">
    @csrf
    @if($address->exists) @method('PATCH') @endif
    <h1>{{ $address->exists ? 'Adresini düzenle' : 'Yeni adres ekle' }}</h1>
    @php $prefill = $address; @endphp
    @include('store.partials.customer-address-fields')
    <label class="checkout-check"><input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default))><span>Varsayılan adresim olsun</span></label>
    @if($errors->any())<div class="checkout-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
    <button class="checkout-action" type="submit">Adresi kaydet</button>
  </form>
</main>
@endsection
