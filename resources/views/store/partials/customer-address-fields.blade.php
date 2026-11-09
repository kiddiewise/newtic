@php
  $prefill = $prefill ?? null;
  $currentType = old('invoice_type', $prefill?->invoice_type ?? 'individual');
@endphp
<div class="checkout-field-grid">
  <label class="checkout-field">
    <span>Adres adı *</span>
    <select name="address_label" required>
      @foreach(['Ev','İş','Diğer'] as $label)
        <option value="{{ $label }}" @selected(old('address_label', $prefill?->label ?? 'Ev') === $label)>{{ $label }}</option>
      @endforeach
    </select>
  </label>
  <label class="checkout-field">
    <span>Ad Soyad *</span>
    <input name="full_name" type="text" maxlength="160" minlength="3" autocomplete="name" required placeholder="Ad Soyad"
      value="{{ old('full_name', $prefill?->full_name ?? (auth()->user()?->name ?? '')) }}">
  </label>
  <label class="checkout-field">
    <span>Cep telefonu *</span>
    <input name="phone" type="tel" maxlength="20" inputmode="tel" autocomplete="tel" required placeholder="05XXXXXXXXX"
      value="{{ old('phone', $prefill?->phone) }}">
  </label>
  <label class="checkout-field">
    <span>İl *</span>
    <select name="city" required autocomplete="address-level1">
      <option value="">İl seçin</option>
      @foreach($provinces as $province)
        <option value="{{ $province }}" @selected(old('city', $prefill?->city) === $province)>{{ $province }}</option>
      @endforeach
    </select>
  </label>
  <label class="checkout-field">
    <span>İlçe *</span>
    <input name="district" type="text" required maxlength="100" autocomplete="address-level2" placeholder="İlçe"
      value="{{ old('district', $prefill?->district) }}">
  </label>
  <label class="checkout-field is-full">
    <span>Açık adres *</span>
    <textarea name="street_address" rows="4" minlength="10" maxlength="1200" autocomplete="street-address" required
      placeholder="Mahalle, cadde, sokak, bina ve daire numarası">{{ old('street_address', $prefill?->street_address) }}</textarea>
  </label>
</div>
<div class="checkout-invoice" data-checkout-invoice>
  <h3>Fatura bilgileri</h3>
  <div class="checkout-invoice-options">
    <label><input type="radio" name="invoice_type" value="individual" @checked($currentType === 'individual')> Bireysel</label>
    <label><input type="radio" name="invoice_type" value="corporate" @checked($currentType === 'corporate')> Kurumsal</label>
  </div>
  <div class="checkout-field-grid checkout-corporate-fields" data-corporate-fields @if($currentType !== 'corporate') hidden @endif>
    <label class="checkout-field is-full"><span>Şirket ünvanı *</span>
      <input type="text" name="company_title" maxlength="200" placeholder="Şirketin ticari ünvanı" value="{{ old('company_title', $prefill?->company_title) }}">
    </label>
    <label class="checkout-field"><span>Vergi dairesi *</span>
      <input type="text" name="tax_office" maxlength="100" placeholder="Vergi dairesi" value="{{ old('tax_office', $prefill?->tax_office) }}">
    </label>
    <label class="checkout-field"><span>Vergi numarası / T.C. kimlik no *</span>
      <input type="text" name="tax_number" minlength="10" maxlength="11" inputmode="numeric" pattern="[0-9]{10,11}" placeholder="10 veya 11 hane" value="{{ old('tax_number', $prefill?->tax_number) }}">
    </label>
  </div>
</div>
