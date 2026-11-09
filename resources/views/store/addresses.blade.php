@extends('layouts.store')
@section('title', 'Adreslerim | Polaris Kurşun')
@section('content')
<main class="checkout-shell" id="content">
  <div class="checkout-top"><a href="{{ route('account.index') }}" aria-label="Hesabım">‹</a><strong>Adreslerim</strong><span></span></div>
  <div class="checkout-card"><h1>Kayıtlı adresler</h1>
    @if(session('success'))<p class="checkout-success">{{ session('success') }}</p>@endif
    @forelse($addresses as $address)
      <div class="account-address-row">
        <div><strong>{{ $address->label }} @if($address->is_default) · Varsayılan @endif</strong>
        <p>{{ $address->full_name }} · {{ $address->district }}/{{ $address->city }}</p>
        <p>{{ $address->street_address }}</p></div>
        <div class="account-address-actions">
          <a href="{{ route('account.addresses.edit', $address) }}">Düzenle</a>
          <form method="POST" action="{{ route('account.addresses.destroy', $address) }}" onsubmit="return confirm('Adresi silmek istiyor musun?')">@csrf @method('DELETE')<button type="submit">Sil</button></form>
        </div>
      </div>
    @empty<p class="checkout-muted">Henüz adres kaydı yok.</p>
    @endforelse
    <a class="checkout-action" href="{{ route('account.addresses.create') }}">+ Yeni adres ekle</a>
  </div>
</main>
@endsection
