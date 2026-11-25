@php
  $deliveryErrors = $errors->delivery;
  $deliveryOld = $deliveryErrors->any();
  $selectedCity = $deliveryOld ? old('city', $order->city) : $order->city;
  $selectedDistrict = $deliveryOld ? old('district', $order->district) : $order->district;
@endphp
<dialog id="delivery-editor" class="newtic-delivery-dialog" data-delivery-editor @if($deliveryErrors->any()) data-delivery-reopen @endif aria-labelledby="delivery-editor-title" aria-describedby="delivery-editor-help">
  <div class="newtic-delivery-sheet">
    <div class="newtic-delivery-handle" aria-hidden="true"></div>
    <div class="newtic-delivery-dialog-heading">
      <div><h2 id="delivery-editor-title">Müşteri ve teslimat</h2><p id="delivery-editor-help">Yalnızca bu siparişin teslimat bilgileri değişir.</p></div>
      <button type="button" data-delivery-edit-close class="newtic-delivery-close" aria-label="Kapat"><x-admin-icon name="close"/></button>
    </div>
    @if($deliveryErrors->any())<div class="newtic-delivery-error" role="alert">@foreach($deliveryErrors->all() as $message)<div>{{ $message }}</div>@endforeach</div>@endif
    <form method="POST" action="{{ route('admin.orders.delivery',$order) }}" class="newtic-delivery-form" data-tr-address-fields>
      @csrf @method('PATCH')
      <label class="newtic-delivery-wide">Ad soyad<input name="customer_name" type="text" required minlength="3" maxlength="160" autocomplete="off" value="{{ $deliveryOld ? old('customer_name',$order->customer_name) : $order->customer_name }}"></label>
      <label class="newtic-delivery-wide">Telefon<input name="phone" type="tel" inputmode="tel" required maxlength="24" autocomplete="off" value="{{ $deliveryOld ? old('phone',$order->phone) : $order->phone }}"></label>
      <label>İl<select name="city" data-tr-province required>
        <option value="">İl seçin</option>
        @foreach(\App\Support\TurkeyProvinces::ALL as $province)
          <option value="{{ $province }}" @selected($selectedCity === $province)>{{ $province }}</option>
        @endforeach
      </select></label>
      <label>İlçe<select name="district" data-tr-district required>
        <option value="">İlçe seçin</option>
        @foreach(\App\Support\TurkeyDistricts::forProvince(is_string($selectedCity) ? $selectedCity : null) as $district)
          <option value="{{ $district }}" @selected($selectedDistrict === $district)>{{ $district }}</option>
        @endforeach
      </select></label>
      <script type="application/json" data-tr-district-map>@json(\App\Support\TurkeyDistricts::all())</script>
      <label class="newtic-delivery-wide">Açık adres<textarea name="street_address" required minlength="10" maxlength="1200" rows="3">{{ $deliveryOld ? old('street_address',$order->street_address) : $order->street_address }}</textarea></label>
      <p class="newtic-delivery-wide newtic-delivery-edit-note">E-posta, üyelik ve ödeme kayıtları değişmez.</p>
      <div class="newtic-delivery-footer newtic-delivery-wide">
        <button type="button" class="newtic-delivery-cancel" data-delivery-edit-close>Vazgeç</button>
        <button type="submit" class="newtic-delivery-save"><x-admin-icon name="check"/> Kaydet</button>
      </div>
    </form>
  </div>
</dialog>
