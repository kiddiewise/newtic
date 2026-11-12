<section class="admin-panel ship-order-panel" aria-label="Entegratör üzerinden gönderi yönetimi">
  <div class="admin-editor-heading">
    <span class="admin-editor-heading-icon is-blue"><x-admin-icon name="truck"/></span>
    <div><h2>Kargo Entegrasyonu</h2><p>Entegratör üzerinden barkod, depo ve takip yönetimi</p></div>
  </div>

  @if($order->shippingShipment)
    @php $shipment = $order->shippingShipment; @endphp
    <div class="ship-order-facts">
      <div><span>Entegratör</span><strong>Kargo Entegratör</strong></div>
      <div><span>Kargo firması</span><strong>{{ $shipment->carrier->name }}</strong></div>
      <div><span>Depo</span><strong>{{ $shipment->warehouse->name }}</strong></div>
      <div><span>İşlem durumu</span><strong>{{ match($shipment->state){'submitted'=>'Gönderi oluşturuldu','uncertain'=>'Kontrol gerekli','creating'=>'İşleniyor',default=>$shipment->state} }}</strong></div>
      <div><span>Kargo süreci</span><strong>{{ $shipment->remote_status ?: 'Bekleniyor' }}</strong></div>
      @if($shipment->barcode)<div><span>Barkod</span><strong>{{ $shipment->barcode }}</strong></div>@endif
      @if($shipment->tracking_number)<div><span>Takip numarası</span><strong>{{ $shipment->tracking_number }}</strong></div>@endif
      <div><span>Desi</span><strong>{{ $shipment->desi }} desi</strong></div>
    </div>
    @if($shipment->state === 'uncertain')
      <p class="ship-danger">Gönderi sonucu doğrulanamadı. Aynı sipariş için tekrar gönderi oluşturma. Kargo Entegratör panelinde bu sipariş numarasıyla kontrol ederek destek al.</p>
    @elseif($shipment->state === 'submitted' && $shippingTestAllowed && $shipment->carrier->is_dummy)
      <div class="ship-order-actions">
        <form method="POST" action="{{ route('admin.shipping-shipments.refresh', $order) }}">
          @csrf
          <button class="admin-secondary" type="submit"><x-admin-icon name="refresh-cw"/> Kargo durumunu yenile</button>
        </form>
        <a class="admin-primary" href="{{ route('admin.shipping-shipments.label', $order) }}"><x-admin-icon name="package"/> Barkod PDF indir</a>
      </div>
      <p class="admin-description">Bu bir Dummy Kargo test gönderisidir. Barkod üretildi diye sipariş gerçek kargo firmasına teslim edilmiş sayılmaz.</p>
    @endif
  @elseif(!$shippingTestAllowed || !$order->is_test)
    <p class="ship-info">Canlı gönderi entegrasyonu henüz etkin değil. Güvenli testler tamamlandıktan sonra DHL ve diğer firmalar için kontrollü açılacak.</p>
  @elseif($order->status !== 'preparing')
    <p class="ship-info">Gönderi oluşturmak için önce sipariş ödemesinin doğrulanması ve durumun “Hazırlanıyor” olması gerekir.</p>
  @elseif(!$shippingProvider?->is_enabled)
    <p class="ship-info">Henüz etkin bir Kargo Entegratör test bağlantısı yok. Önce API ayarlarını ve Dummy Kargo hesabını kur.</p>
    <a class="admin-secondary" href="{{ route('admin.shipping-integrations.index') }}">Kargo entegrasyonuna git</a>
  @else
    <p class="ship-info">Yalnızca Dummy Kargo ile test barkodu oluşturulur. DHL ve diğer gerçek kargo firmaları henüz aktif değildir.</p>
    @if($shippingCarriers->where('is_dummy', true)->isNotEmpty() && $shippingWarehouses->isNotEmpty())
      <form class="admin-form ship-order-form" action="{{ route('admin.shipping-shipments.create', $order) }}" method="POST"
        onsubmit="return confirm('Bu test siparişi için Dummy Kargo barkodu oluşturulsun mu? İşlem tekrar gönderilemez.')">
        @csrf
        <label>Kargo firması
          <select name="carrier_id" required>
            @foreach($shippingCarriers as $carrier)
              <option value="{{ $carrier->id }}" @disabled(!$carrier->is_dummy)>{{ $carrier->name }}{{ $carrier->is_dummy ? ' · Dummy test' : ' · Canlı gönderi kapalı' }}</option>
            @endforeach
          </select>
        </label>
        <label>Gönderici depo
          <select name="warehouse_id" required>
            @foreach($shippingWarehouses as $warehouse)<option value="{{ $warehouse->id }}">{{ $warehouse->name }}</option>@endforeach
          </select>
        </label>
        <label>Paket desisi (zorunlu)
          <input type="number" name="desi" min="0.01" max="99999" step="0.01" required value="{{ old('desi', '1.00') }}">
          <small>Ürün gramajı, paket desisi değildir. Paketledikten sonra kontrol et.</small>
        </label>
        <label>Paket ağırlığı (kg, isteğe bağlı)
          <input type="number" name="kg" min="0.01" max="99999" step="0.01" value="{{ old('kg') }}" placeholder="0.25">
        </label>
        <label>Kargo bedelinin ödeme türü
          <select name="payment_type" required>
            <option value="cash_money" selected>Nakit / Havale</option>
            <option value="credit_card">Kredi kartı</option>
          </select>
        </label>
        <p class="admin-description">Kargo bedeli göndericiye ait olacaktır; kapıda ödeme yapılmayacaktır. Ödeme şekli müşteri siparişi için değil, taşıma bedeli içindir.</p>
        <button class="admin-primary" type="submit"><x-admin-icon name="truck"/> Dummy Kargo barkodu oluştur</button>
      </form>
    @else
      <p class="admin-description">En az bir Dummy Kargo hesabı ve depo gerekli. Ayarlardan senkronize edebilirsin.</p>
    @endif
  @endif
</section>
