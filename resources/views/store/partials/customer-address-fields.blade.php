@php
  $prefill = $prefill ?? null;
  $currentType = old('invoice_type', $prefill?->invoice_type ?? 'individual');
@endphp
<div class="checkout-field-grid">
  <label class="checkout-field">
    <span>Adres adı *</span>
    <select name="address_label" aria-invalid="{{ $errors->has('address_label') ? 'true' : 'false' }}" @error('address_label') aria-describedby="checkout-error-address_label" @enderror required>
      @foreach(['Ev','İş','Diğer'] as $label)
        <option value="{{ $label }}" @selected(old('address_label', $prefill?->label ?? 'Ev') === $label)>{{ $label }}</option>
      @endforeach
    </select>
  @error('address_label')<small class="checkout-field-error" id="checkout-error-address_label">{{ $message }}</small>@enderror
    </label>
  <label class="checkout-field">
    <span>Ad Soyad *</span>
    <input name="full_name" aria-invalid="{{ $errors->has('full_name') ? 'true' : 'false' }}" @error('full_name') aria-describedby="checkout-error-full_name" @enderror type="text" maxlength="160" minlength="3" autocomplete="name" required placeholder="Ad Soyad"
      value="{{ old('full_name', $prefill?->full_name ?? (auth()->user()?->name ?? '')) }}">
  @error('full_name')<small class="checkout-field-error" id="checkout-error-full_name">{{ $message }}</small>@enderror
    </label>
  <label class="checkout-field">
    <span>Cep telefonu *</span>
    <span class="tr-phone-control" data-tr-phone-control>
      <span class="tr-phone-prefix" aria-hidden="true"><span class="tr-phone-flag">🇹🇷</span><span class="tr-phone-dial">+90</span></span>
      <input name="phone" data-tr-mobile-phone
        aria-label="Cep telefonu, Türkiye artı doksan"
        aria-invalid="{{ $errors->has('phone') ? 'true' : 'false' }}"
        aria-describedby="checkout-phone-hint{{ $errors->has('phone') ? ' checkout-error-phone' : '' }}"
        type="tel" maxlength="32" inputmode="numeric" autocomplete="tel" enterkeyhint="next"
        required placeholder="555 555 55 55"
        value="{{ \App\Support\AddressValidation::formatForEntry(old('phone', $prefill?->phone)) }}">
    </span>
  <small class="checkout-field-hint" id="checkout-phone-hint">Numaranı 555 555 55 55 şeklinde gir. Kargo görevlisi gerektiğinde sana ulaşabilir.</small>
    @error('phone')<small class="checkout-field-error" id="checkout-error-phone">{{ $message }}</small>@enderror
    </label>
  <label class="checkout-field">
    <span>İl *</span>
    <select name="city" aria-invalid="{{ $errors->has('city') ? 'true' : 'false' }}" @error('city') aria-describedby="checkout-error-city" @enderror required autocomplete="address-level1">
      <option value="">İl seçin</option>
      @foreach($provinces as $province)
        <option value="{{ $province }}" @selected(old('city', $prefill?->city) === $province)>{{ $province }}</option>
      @endforeach
    </select>
  @error('city')<small class="checkout-field-error" id="checkout-error-city">{{ $message }}</small>@enderror
    </label>
  <label class="checkout-field">
    <span>İlçe *</span>
    <input name="district" aria-invalid="{{ $errors->has('district') ? 'true' : 'false' }}" @error('district') aria-describedby="checkout-error-district" @enderror type="text" required maxlength="100" autocomplete="address-level2" placeholder="İlçe"
      value="{{ old('district', $prefill?->district) }}">
  @error('district')<small class="checkout-field-error" id="checkout-error-district">{{ $message }}</small>@enderror
    </label>
  <label class="checkout-field is-full">
    <span>Açık adres *</span>
    <textarea name="street_address" aria-invalid="{{ $errors->has('street_address') ? 'true' : 'false' }}" @error('street_address') aria-describedby="checkout-error-street_address" @enderror rows="4" minlength="10" maxlength="1200" autocomplete="street-address" required
      placeholder="Mahalle, cadde, sokak, bina ve daire numarası">{{ old('street_address', $prefill?->street_address) }}</textarea>
  @error('street_address')<small class="checkout-field-error" id="checkout-error-street_address">{{ $message }}</small>@enderror
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
      <input type="text" name="company_title" aria-invalid="{{ $errors->has('company_title') ? 'true' : 'false' }}" @error('company_title') aria-describedby="checkout-error-company_title" @enderror maxlength="200" placeholder="Şirketin ticari ünvanı" value="{{ old('company_title', $prefill?->company_title) }}">
    @error('company_title')<small class="checkout-field-error" id="checkout-error-company_title">{{ $message }}</small>@enderror
    </label>
    <label class="checkout-field"><span>Vergi dairesi *</span>
      <input type="text" name="tax_office" aria-invalid="{{ $errors->has('tax_office') ? 'true' : 'false' }}" @error('tax_office') aria-describedby="checkout-error-tax_office" @enderror maxlength="100" placeholder="Vergi dairesi" value="{{ old('tax_office', $prefill?->tax_office) }}">
    @error('tax_office')<small class="checkout-field-error" id="checkout-error-tax_office">{{ $message }}</small>@enderror
    </label>
    <label class="checkout-field"><span>Vergi numarası / T.C. kimlik no *</span>
      <input type="text" name="tax_number" aria-invalid="{{ $errors->has('tax_number') ? 'true' : 'false' }}" @error('tax_number') aria-describedby="checkout-error-tax_number" @enderror minlength="10" maxlength="11" inputmode="numeric" pattern="[0-9]{10,11}" placeholder="10 veya 11 hane" value="{{ old('tax_number', $prefill?->tax_number) }}">
    @error('tax_number')<small class="checkout-field-error" id="checkout-error-tax_number">{{ $message }}</small>@enderror
    </label>
  </div>
</div>
