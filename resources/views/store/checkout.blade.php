@extends('layouts.store')
@section('title', 'Siparişi Tamamla | Polaris Kurşun')
@section('content')
@php $checkoutIsTest = str_contains((string) config('app.url'), 'hostingersite.com'); @endphp
<main class="checkout-shell checkout-page" id="content">
  <div class="checkout-top"><a href="{{ route('cart.index') }}" aria-label="Sepete dön">‹</a><strong>Siparişi Tamamla</strong><span class="checkout-stage-label">2 / 3</span></div>
  <nav class="checkout-steps" aria-label="Sipariş aşamaları">
    <a href="{{ route('cart.index') }}"><span aria-hidden="true">✓</span> Sepet</a>
    <span aria-current="step"><span aria-hidden="true">2</span> Teslimat ve ödeme</span>
    <span><span aria-hidden="true">3</span> Onay</span>
  </nav>
  <div class="checkout-main-grid">
    <div class="checkout-form-side">
      <details class="checkout-mobile-review">
        <summary><span>Sipariş özetini gör <small>{{ $cart['count'] }} ürün · Kargo dahil</small></span><strong>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</strong><x-store-icon name="chevron-down" /></summary>
        <div class="checkout-mobile-review-body">
          @foreach($cart['lines'] as $line)
            <div class="checkout-order-item"><span>{{ $line['product']->name }} <small>× {{ $line['quantity'] }}</small></span><strong>{{ number_format($line['line_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
          @endforeach
          <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($cart['subtotal_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
          <div class="checkout-price-line"><span>Kargo</span><strong>{{ $cart['shipping_cents'] === 0 ? 'Ücretsiz' : number_format($cart['shipping_cents'] / 100, 2, ',', '.').' ₺' }}</strong></div>
          <div class="checkout-price-line is-total"><span>Toplam</span><strong>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
          <a href="{{ route('cart.index') }}" class="checkout-inline-link">Sepetimi düzenle</a>
        </div>
      </details>
      @guest
<section class="checkout-card checkout-member-invite checkout-guest-invite">
          <span class="checkout-guest-tag"><x-store-icon name="check" /> Üyelik gerekmiyor</span>
          <h2>Misafir olarak devam et</h2>
          <p>Hesap açmadan sipariş oluşturabilirsin. Bilgilerini aşağıya girmen yeterli.</p>
          <div class="checkout-guest-login">Zaten üye misin? <a href="{{ route('account.login', ['next' => 'checkout']) }}">Giriş yap</a></div>
        </section>
      @else
        <section class="checkout-card checkout-member-invite">
          <h2>Hoş geldin, {{ auth()->user()->name }}</h2>
          <p>Kayıtlı adreslerinden birini seçebilir veya yeni adres girebilirsin.</p>
          @if($addresses->isNotEmpty())
            <label class="checkout-field"><span>Kayıtlı adreslerim</span>
              <select data-checkout-saved-address aria-label="Kayıtlı adres seç">
                <option value="">Yeni adres / Bilgileri kendim gireceğim</option>
                @foreach($addresses as $address)
                  <option value="{{ $address->id }}" @selected($selectedAddress?->id === $address->id)
                    data-address-label="{{ $address->label }}"
                    data-full-name="{{ $address->full_name }}"
                    data-phone="{{ $address->phone }}"
                    data-city="{{ $address->city }}"
                    data-district="{{ $address->district }}"
                    data-street-address="{{ $address->street_address }}"
                    data-invoice-type="{{ $address->invoice_type }}"
                    data-company-title="{{ $address->company_title }}"
                    data-tax-office="{{ $address->tax_office }}"
                    data-tax-number="{{ $address->tax_number }}"
                  >{{ $address->label }} — {{ $address->district }}/{{ $address->city }}</option>
                @endforeach
              </select>
            </label>
          @endif
          <a class="checkout-inline-link" href="{{ route('account.addresses.index') }}">Adres defterimi yönet</a>
        </section>
      @endguest

      <form id="checkout-form" method="POST" action="{{ route('checkout.place') }}" class="checkout-form checkout-card">
        @csrf
        <input type="hidden" name="checkout_token" value="{{ $checkoutToken }}">
        <input type="hidden" name="payment_method" value="bank_transfer">
        <div class="checkout-form-intro">
          <span>TESLİMAT BİLGİLERİ</span>
<h1>Teslimat bilgilerin</h1>
          <p>Yıldızlı alanları doldur. Sipariş vermek için hesap oluşturman gerekmez.</p>
        </div>

        @if($errors->any())
          <div class="checkout-error" role="alert">
            <strong>Lütfen bilgileri kontrol et:</strong>
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
          </div>
        @endif

<label class="checkout-field is-full"><span>E-posta adresi *</span>
          <input type="email" name="email" maxlength="255" autocomplete="email" id="checkout-email"
            placeholder="ornek@eposta.com" required aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" @if($errors->has('email')) aria-describedby="checkout-error-email" @endif value="{{ old('email', auth()->user()?->email) }}">
          @error('email')<small class="checkout-field-error" id="checkout-error-email">{{ $message }}</small>@enderror
          <small class="checkout-field-hint">Sipariş numaranı ve durumunu bildirmek için kullanılır.</small>
        </label>

        @php $prefill = $selectedAddress; @endphp
        @include('store.partials.customer-address-fields')

        <section class="checkout-extra-section">
          <h3>Sipariş notu <small>(isteğe bağlı)</small></h3>
          <textarea name="order_note" rows="3" maxlength="2000" placeholder="Teslimatla ilgili bir notunuz varsa yazın.">{{ old('order_note') }}</textarea>
        </section>

        @auth
          <label class="checkout-check"><input type="checkbox" name="save_address" value="1" @checked(old('save_address', !$selectedAddress))>
            <span>Bu adresi hesabıma kaydet (Ev, İş veya Diğer)</span>
          </label>
        @else
          <section class="checkout-extra-section checkout-create-account">
            <label class="checkout-check"><input type="checkbox" name="create_account" value="1" data-create-account @checked(old('create_account'))>
              <span><strong>Yeni üyelik oluştur</strong><small>Bir sonraki alışverişinde adreslerini ve siparişlerini hesabında gör.</small></span>
            </label>
            <div class="checkout-register-fields" data-create-account-fields @if(!old('create_account')) hidden @endif>
              <label class="checkout-field"><span>Hesap şifresi (en az 8 karakter) *</span>
                <input type="password" name="account_password" minlength="8" autocomplete="new-password">
              </label>
            </div>
          </section>
        @endauth

        <section class="checkout-payment-method">
          <div class="checkout-section-title"><span>ÖDEME</span><h2>Ödeme yöntemi</h2></div>
<div class="checkout-payment-option"><span class="checkout-payment-dot"></span>
            <div><strong>Havale / EFT</strong><small>Henüz banka hesabı tanımlı değil. Banka bilgileri sağlanana kadar ödeme yapmayın.</small></div>
          </div>
          <p class="checkout-testing-note"><strong>{{ $checkoutIsTest ? 'Deneme ortamı:' : 'Önemli:' }}</strong> {{ $checkoutIsTest ? 'Siparişiniz yalnızca test kaydı olarak oluşturulur; gerçek ödeme alınmaz.' : 'Ödeme altyapısı henüz etkin değil. Sipariş kaydı oluşturmanız ödeme yapıldığı anlamına gelmez.' }}</p>
        </section>
        <p class="checkout-privacy">Sipariş bilgileri yalnızca teslimat, faturalama ve sipariş yönetimi amacıyla alınır. Pazarlama izni otomatik verilmez.</p>
<button class="checkout-action checkout-desktop-submit" type="submit" data-checkout-submit>{{ $checkoutIsTest ? 'Deneme Siparişini Oluştur' : 'Siparişi Oluştur' }}</button>
      </form>
    </div>

    <aside class="checkout-card checkout-order-summary" aria-label="Sipariş özeti">
<div class="checkout-summary-heading"><h2>Sipariş Özeti</h2><a href="{{ route('cart.index') }}">Sepeti düzenle</a></div>
      @foreach($cart['lines'] as $line)
        <div class="checkout-order-item">
          <span>{{ $line['product']->name }} <small>× {{ $line['quantity'] }}</small></span>
          <strong>{{ number_format($line['line_cents'] / 100, 2, ',', '.') }} ₺</strong>
        </div>
      @endforeach
      <div class="checkout-price-line"><span>Ürünler</span><strong>{{ number_format($cart['subtotal_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
      <div class="checkout-price-line"><span>Kargo</span><strong>{{ $cart['shipping_cents'] === 0 ? 'Ücretsiz' : number_format($cart['shipping_cents'] / 100, 2, ',', '.').' ₺' }}</strong></div>
      <div class="checkout-price-line is-total"><span>Ödenecek tutar</span><strong>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</strong></div>
    </aside>
  </div>
</main>
<div class="checkout-bottom-bar" aria-label="Siparişi tamamla">
  <div><strong>{{ number_format($cart['total_cents'] / 100, 2, ',', '.') }} ₺</strong><span>{{ $cart['shipping_cents'] === 0 ? 'Kargo ücretsiz' : 'Kargo dahil' }}</span></div>
<button type="submit" form="checkout-form" data-checkout-submit>{{ $checkoutIsTest ? 'Deneme Siparişini Oluştur' : 'Siparişi Oluştur' }}</button>
</div>
@endsection
