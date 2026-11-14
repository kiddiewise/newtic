@extends('admin.layout')
@section('title', 'Banka Hesapları | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">ÖDEME AYARLARI</p><h1>Banka hesapları</h1>
    <p>Havale/EFT için birden fazla IBAN tanımla, banka logosunu yükle, sıralamayı ve görünürlüğü yönet.</p></div>
</div>
<div class="admin-panel">
  <h2>Yeni banka hesabı</h2>
  <p class="admin-description">Hesap bilgileri yalnızca sipariş sonrası ödeme talimatlarında gösterilir. IBAN kontrol hanesi otomatik doğrulanır.</p>
  <form class="admin-form bank-admin-form" method="POST" action="{{ route('admin.bank-accounts.store') }}" enctype="multipart/form-data">
    @csrf
    <label>Banka adı <input name="bank_name" maxlength="120" required placeholder="Örn. Banka adı" value="{{ old('bank_name') }}"></label>
    <label>Hesap sahibinin adı / unvanı <input name="account_holder" maxlength="190" required value="{{ old('account_holder') }}"></label>
    <label class="bank-admin-wide">IBAN (TR, 26 karakter) <input name="iban" maxlength="34" spellcheck="false" autocomplete="off" required placeholder="TR ile başlayan IBAN" value="{{ old('iban') }}"></label>
    <label>Banka logosu (PNG / JPG / WebP, en fazla 2 MB) <input name="logo" type="file" accept="image/png,image/jpeg,image/webp"></label>
    <label>Sıra <input name="sort_order" type="number" min="0" max="99999" value="{{ old('sort_order', 0) }}" required></label>
    <label class="admin-check bank-admin-wide"><input name="is_active" type="checkbox" value="1" @checked(old('is_active', true))> Müşterilere göster (aktif)</label>
    <div class="admin-form-actions"><button class="admin-primary" type="submit">Banka hesabı ekle</button></div>
  </form>
</div>
<div class="admin-panel">
  <h2>Kayıtlı banka hesapları ({{ $accounts->count() }})</h2>
  @forelse($accounts as $account)
    <div class="bank-admin-entry">
      <div class="bank-admin-title">
        @if($account->logo_path)<img src="{{ asset($account->logo_path) }}" alt="{{ $account->bank_name }} logosu" width="64" height="48">@else<span class="bank-admin-placeholder">Banka</span>@endif
        <div><strong>{{ $account->bank_name }}</strong><small>{{ $account->account_holder }}</small></div>
        <span class="admin-status {{ $account->is_active ? 'is-active' : '' }}">{{ $account->is_active ? 'Aktif' : 'Pasif' }}</span>
      </div>
      <form class="admin-form bank-admin-form" method="POST" action="{{ route('admin.bank-accounts.update', $account) }}" enctype="multipart/form-data">
        @csrf @method('PATCH')
        <label>Banka adı <input name="bank_name" maxlength="120" required value="{{ $account->bank_name }}"></label>
        <label>Hesap sahibi <input name="account_holder" maxlength="190" required value="{{ $account->account_holder }}"></label>
        <label class="bank-admin-wide">IBAN <input name="iban" maxlength="34" spellcheck="false" autocomplete="off" required value="{{ $account->iban }}"></label>
        <label>Logoyu değiştir <input type="file" name="logo" accept="image/png,image/jpeg,image/webp"></label>
        <label>Sıra <input type="number" name="sort_order" min="0" max="99999" required value="{{ $account->sort_order }}"></label>
        @if($account->logo_path)<label class="admin-check bank-admin-wide"><input type="checkbox" name="remove_logo" value="1"> Mevcut logoyu kaldır</label>@endif
        <label class="admin-check bank-admin-wide"><input name="is_active" type="checkbox" value="1" @checked($account->is_active)> Müşterilere göster (aktif)</label>
        <div class="admin-form-actions"><button class="admin-primary" type="submit">Değişiklikleri kaydet</button></div>
      </form>
    </div>
  @empty
    <p class="admin-description">Henüz banka hesabı eklenmedi. Hesap eklenene kadar müşteriye ödeme IBAN'ı gösterilmez.</p>
  @endforelse
</div>
@endsection
