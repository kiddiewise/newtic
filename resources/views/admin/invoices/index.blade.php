@extends('admin.layout')
@section('title','Fatura Yönetim Merkezi | NEWTIC')
@section('content')
<div class="admin-page-heading">
  <div><p class="admin-eyebrow">SİPARİŞ YÖNETİMİ</p><h1>Fatura Yönetim Merkezi</h1><p>Fatura bekleyen siparişler ve EDM işlem kayıtları</p></div>
  <a class="admin-secondary" href="{{ route('admin.edm.edit') }}">EDM bağlantı ayarları</a>
</div>
<section class="admin-panel">
  <h2>Faturalandırma ayarları</h2>
  <form method="POST" action="{{ route('admin.invoices.buyer-check') }}" class="admin-form">@csrf
    <label>Alıcı mükellefiyet kontrolü<input name="identifier" inputmode="numeric" pattern="[0-9]{10,11}" maxlength="11" required autocomplete="off"></label>
    <button type="submit" class="admin-secondary">Belge türünü belirle</button>
  </form>
  <form method="POST" action="{{ route('admin.invoices.settings') }}" class="admin-form">@csrf
    <input type="hidden" name="auto_invoice" value="0">
    <label class="admin-check"><input type="checkbox" name="auto_invoice" value="1" @checked($auto)> Sipariş tamamlandığında otomatik fatura oluştur</label>
    <p>Varsayılan manuel. Otomatik seçim etkinleştirildikten sonraki tamamlanma kayıtlarına uygulanır; önceki siparişleri topluca işlemez.</p>
    <label>Kargo KDV oranı<select name="shipping_vat_rate"><option value="">Belirlenmedi</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}" @selected((int)$shippingVat===$rate)>%{{ $rate }}</option>@endforeach</select></label>
    <button type="submit" class="admin-secondary">Ayarları kaydet</button>
  </form>
  @if(!\App\Services\Invoices\EdmStoreScope::sandboxOrders())
    <p role="status">Test siparişleri için EDM gönderimi kapalı. Canlı e-Fatura/e-Arşiv gönderimi henüz desteklenmiyor.</p>
  @elseif(!$dispatchReady)
    <p role="status">EDM belge yükleme doğrulaması ve onaylı şablon bekleniyor. Oluşturma istekleri saklanır; doğrulama tamamlanmadan gönderilmez.</p>
  @endif
</section>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>Fatura kesilmesi bekleyen siparişler ({{ $pending->total() }})</h2></div>
  <form method="POST" id="invoice-bulk" action="{{ route('admin.invoices.create') }}">@csrf</form>
  <div class="admin-form">
    <label>Kargo KDV oranı (gerekirse)<select name="shipping_rate" form="invoice-bulk"><option value="">Kayıtlı oranı kullan</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>
    <label>Eksik ürün KDV oranı (eski siparişler)<select name="missing_product_rate" form="invoice-bulk"><option value="">Yalnızca kayıtlı ürün oranlarını kullan</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>
    <p>Mevcut fatura oluşturma akışı e-Fatura test taslağıdır. e-Arşiv için sipariş ön kontrolünü kullanın; bu sürümde e-Arşiv yüklemesi açılmaz.</p>
    <p>Seçim yalnızca KDV bilgisi boş ürün satırlarını tamamlar. Mevcut oranları değiştirmez. Farklı oranlı eski ürünler için siparişleri ayrı ayrı kontrol edin.</p>
    <button class="admin-primary" type="submit" form="invoice-bulk" @disabled(!$canQueue||$pending->isEmpty())>Seçilen faturaları oluştur</button>
  </div>
  @forelse($pending as $order)
    <article style="padding:12px 0;border-bottom:1px solid var(--admin-border,#ddd)">
      <label class="admin-check"><input type="checkbox" name="orders[]" value="{{ $order->id }}" form="invoice-bulk" @disabled(isset($archiveMetadata[$order->id]))> {{ $order->order_number }} · {{ $order->statusLabel() }}</label>
      <div class="admin-detail-line"><a href="{{ route('admin.orders.show',$order) }}">Siparişi aç</a><strong>{{ number_format($order->total_cents/100,2,',','.') }} TL</strong></div>
      @if(isset($archiveMetadata[$order->id]))
        <p role="status">e-Arşiv hazırlığı kaydedildi · Seri {{ $archiveMetadata[$order->id]->series }}. e-Fatura yüklemesi kapalı; e-Arşiv aktarımı henüz açılmadı.</p>
      @endif
      @if($canQueue && !isset($archiveMetadata[$order->id]))
        <details style="margin:12px 0"><summary>Belge türü ve e-Arşiv ön kontrolü</summary>
          <form method="POST" action="{{ route('admin.invoices.order-preflight',['order'=>$order->id]) }}" class="admin-form">@csrf
            <label>EDM test gönderici VKN/TCKN<input name="seller_id" inputmode="numeric" pattern="[0-9]{10,11}" maxlength="11" required autocomplete="off" value="{{ \App\Services\Invoices\EdmStoreScope::website()===\App\Services\Invoices\EdmSandboxSmokeFixture::STAGING_URL ? \App\Services\Invoices\EdmSandboxSmokeFixture::IDENTIFIER : '' }}"></label>
            <label class="admin-check"><input type="checkbox" name="synthetic_confirm" value="1" required> Bu sipariş yalnızca sentetik test verisi içeriyor; mükellef sorgusunu onaylıyorum.</label>
            <label class="admin-check"><input type="checkbox" name="anonymous_test" value="1"> Kimliği olmayan sentetik bireysel alıcı için e-Arşiv teknik prova adayı</label>
            <details><summary>Doğrulanmış ödeme ve sevk bilgileri (isteğe bağlı)</summary>
              <p>Gerçek işlem tarihi ve taşıyıcının yasal bilgileri kayıtlı değilse yalnızca doğrulanmış kaynaklardan girin. Ön kontrol varsayılan olarak kayıt yapmaz.</p>
              <label>Ödeme tarihi<input type="date" name="payment_date"></label>
              <label>Sevk tarihi<input type="date" name="dispatch_date"></label>
              <label>Taşıyıcı VKN<input name="carrier_vkn" inputmode="numeric" pattern="[0-9]{10}" maxlength="10" autocomplete="off"></label>
              <label>Taşıyıcı yasal unvanı<input name="carrier_title" maxlength="255"></label>
              <label class="admin-check"><input type="checkbox" name="confirm_metadata" value="1"> Bu test ödeme ve sevk bilgilerini doğruladım.</label>
            </details>
            <label>Doğrulanmış e-Arşiv seri adayı (kayıt için)<input name="archive_series" pattern="[A-Z0-9]{3}" maxlength="3" autocomplete="off"></label>
            <label class="admin-check"><input type="checkbox" name="save_metadata" value="1"> Doğrulanan e-Arşiv bilgilerini bu sipariş için kaydet; e-Fatura gönderimini kapat.</label>
            <button class="admin-secondary" type="submit">Sipariş bilgilerini kontrol et</button>
            <small>Yalnızca ön kontrol; fatura numarası ayrılmaz ve belge gönderilmez.</small>
          </form>
        </details>
      @endif
      <form method="POST" action="{{ route('admin.invoices.create') }}" class="admin-form">@csrf
        <input type="hidden" name="orders[]" value="{{ $order->id }}">
        @if($order->shipping_cents>0)
          <label>Kargo KDV<select name="shipping_rate" @required($shippingVat===null)><option value="">Oran seçin</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}" @selected((int)$shippingVat===$rate)>%{{ $rate }}</option>@endforeach</select></label>
        @endif
        @if($order->missing_vat_count>0)
          <p role="status">{{ $order->missing_vat_count }} ürün satırında geçmiş KDV kaydı eksik.</p>
          @foreach($order->items as $item)
            <small>{{ $item->product_name }} — Güncel ürün KDV: {{ $item->product?->vat_rate!==null ? '%'.$item->product->vat_rate : 'Bilinmiyor' }}</small>
          @endforeach
          <label>Eksik ürün KDV oranını onayla<select name="missing_product_rate" required><option value="">Oran seçin</option>@foreach([1,10,20] as $rate)<option value="{{ $rate }}">%{{ $rate }}</option>@endforeach</select></label>
          <small>Seçilen oran sadece yukarıdaki eksik satırlara uygulanır. Mevcut oranlar korunur.</small>
        @endif
        <button type="submit" class="admin-secondary" @disabled(!$canQueue||isset($archiveMetadata[$order->id]))>Fatura oluştur</button>
      </form>
    </article>
  @empty <p>Fatura bekleyen uygun sipariş yok.</p>@endforelse
  {{ $pending->links() }}
</section>
<section class="admin-panel" style="margin-top:16px">
  <h2>Fatura işlem kuyruğu</h2>
  @forelse($dispatches as $dispatch)
    <div class="admin-detail-line"><a href="{{ route('admin.orders.show',['order'=>$dispatch->order_id]) }}">{{ $dispatch->order_number }}</a><strong>{{ ['queued'=>'Sırada','processing'=>'İşleniyor','acknowledged'=>'EDM taslak onayı','blocked'=>'İşlem engellendi','uncertain'=>'Sonuç doğrulanamadı'][$dispatch->state] ?? 'Bilinmiyor' }}</strong></div>
    @if($dispatch->diagnostic)<p>{{ $dispatch->diagnostic }}</p>@endif
  @empty <p>Henüz fatura oluşturma isteği yok.</p>@endforelse
  <p>EDM taslak onayı nihai fatura düzenlendi anlamına gelmez. Belirsiz kayıtlar otomatik yeniden gönderilmez.</p>
</section>
@php($labels=['all'=>'Tüm hazırlıklar','local'=>'Yerel hazırlık','uncertain'=>'Sonuç belirsiz','observed'=>'EDM kaydı gözlemlendi'])
<div class="admin-dashboard-grid">
  @foreach($labels as $key=>$label)
    <section class="admin-panel">
      <p>{{ $label }}</p><h2>{{ $counts[$key] }}</h2>
      <a href="{{ route('admin.invoices.index',['state'=>$key]) }}" @if($filter===$key) aria-current="page" @endif>Listele</a>
    </section>
  @endforeach
</div>
<section class="admin-panel" style="margin-top:16px">
  <div class="admin-panel-title"><h2>{{ $labels[$filter] }}</h2></div>
  @forelse($rows as $row)
    <article style="padding:16px 0;border-bottom:1px solid var(--admin-border,#ddd)">
      <div class="admin-detail-line"><span><a href="{{ route('admin.orders.invoice-preview',['order'=>$row->order_id]) }}">{{ $row->order_number }}</a></span><strong>{{ number_format($row->gross_cents/100,2,',','.') }} {{ $row->currency }}</strong></div>
      <div class="admin-detail-line" style="overflow-wrap:anywhere"><span>ETTN</span><strong>{{ $row->uuid }}</strong></div>
      <div class="admin-detail-line"><span>Kayıt</span><strong>{{ $row->attempt_id === null ? 'Yerel teknik hazırlık' : (['outcome_unknown'=>'Sonuç belirsiz — tekrar gönderilmez','provider_record_found'=>'EDM kaydı gözlemlendi'][$row->state] ?? 'Bilinmiyor') }}</strong></div>
      @if($row->attempt_id !== null)
        <div class="admin-detail-line"><span>Senaryo</span><strong>{{ $row->profile }}</strong></div>
        <div class="admin-detail-line"><span>Yükleme yanıtı</span><strong>{{ ['draft_acknowledged'=>'Taslak yanıtı alındı','unconfirmed'=>'Doğrulanamadı'][$row->last_load_result ?? ''] ?? 'Henüz doğrulanmadı' }}</strong></div>
        <div class="admin-detail-line"><span>Fatura numarası</span><strong>{{ $row->observed_invoice_number ?? 'Doğrulanmadı' }}</strong></div>
        <div class="admin-detail-line"><span>Son sorgu</span><strong>{{ ['matched'=>'Eşleşti','unconfirmed'=>'Doğrulanamadı','identity_conflict'=>'Kimlik çelişkisi'][$row->last_lookup_state ?? ''] ?? 'Henüz yapılmadı' }}</strong></div>
        @if($row->can_reconcile)
          <div class="admin-detail-line"><span>EDM belge durumu</span><strong>{{ $row->last_lookup_state!=='matched' ? 'Güncel durum doğrulanmadı' : (['draft'=>'Taslak','packaging'=>'Paketleniyor','package_failed'=>'Paketleme başarısız','sending'=>'Gönderiliyor','waiting_gib'=>'GİB yanıtı bekleniyor','waiting_system'=>'Sistem yanıtı bekleniyor','send_failed'=>'Gönderim başarısız','send_succeeded'=>'Gönderim başarılı (EDM)','waiting_application'=>'Uygulama yanıtı bekleniyor','accepted_succeeded'=>'Kabul edildi','rejected_succeeded'=>'Reddedildi','cancelled_succeeded'=>'İptal edildi','unknown'=>'Tanınmayan durum'][$row->last_portal_state ?? ''] ?? 'Henüz sorgulanmadı') }}</strong></div>
          @if($row->last_lookup_state==='matched' && $row->last_portal_token)
            <div class="admin-detail-line"><span>EDM teknik durum kodu</span><strong>{{ $row->last_portal_token }}</strong></div>
          @endif
        @endif
        <div class="admin-detail-line"><span>GİB zarf kodu</span><strong>{{ $row->last_gib_code ?? 'Bildirilmedi' }}</strong></div>
        <div class="admin-detail-line"><span>Son sorgu zamanı</span><strong>{{ $row->last_lookup_at ?? '—' }}</strong></div>
        @if($row->can_reconcile)
          <form method="POST" action="{{ route('admin.invoices.reconcile',['attempt'=>$row->attempt_id]) }}">@csrf<button type="submit" class="admin-secondary">EDM durumunu güncelle</button></form>
          @if($row->last_load_result==='draft_acknowledged' && $row->state==='provider_record_found' && $row->observed_invoice_number)
            <form method="POST" action="{{ route('admin.invoices.documents.refresh',['attempt'=>$row->attempt_id]) }}">@csrf<button type="submit" class="admin-secondary">PDF / XML belgelerini getir</button></form>
            @foreach($documentChecks[$row->attempt_id] ?? [] as $check)
              <div class="admin-detail-line"><span>{{ strtoupper($check->format) }}</span><strong>{{ $check->state==='matched'?'Alındı':($check->diagnostic??'Doğrulanamadı') }}</strong></div>
            @endforeach
            @foreach($documents[$row->attempt_id] ?? [] as $doc)
              <a class="admin-secondary" href="{{ route('admin.invoices.documents.download',['attempt'=>$row->attempt_id,'format'=>$doc->format]) }}">{{ strtoupper($doc->format) }} indir</a>
            @endforeach
            @if(isset($sendRequests[$row->attempt_id]))
              <p>Gönderim: {{ ['accepted_for_processing'=>'EDM işleme aldı','unconfirmed'=>'Sonuç belirsiz','outcome_unknown'=>'İşlem ayrıldı','transport_started'=>'Gönderim başladı'][$sendRequests[$row->attempt_id]->state] ?? 'Bilinmiyor' }}. Nihai durum için sorgulayın.</p>
            @elseif($row->last_lookup_state==='matched' && $row->last_portal_state==='draft' && !$row->last_gib_code)
              <form method="POST" action="{{ route('admin.invoices.send',['attempt'=>$row->attempt_id]) }}" class="admin-form">@csrf
                <label class="admin-check"><input type="checkbox" name="confirm_send" value="1" required> Bu taslağın EDM deneme ortamında nihai gönderimini onaylıyorum.</label>
                <button type="submit" class="admin-primary">EDM gönderimini başlat</button>
                <small>Tek seferlik işlem. XML ve taslak durumunun doğrulanması gerekir.</small>
              </form>
            @else
              <p role="status">Nihai gönderim şu anda kapalı:
                @if($row->last_lookup_state!=='matched') güncel EDM sorgusu eşleşmedi.
                @elseif($row->last_gib_code) GİB zarf kodu bildirildi; tekrar gönderilmez.
                @else EDM taslak durumu doğrulanmadı. Teknik durum kodunu kontrol edin.
                @endif
              </p>
            @endif
          @endif
        @else <p>Bu kayıt için mevcut EDM hesabıyla sorgulama yapılamıyor.</p> @endif
      @endif
    </article>
  @empty
    <p>Henüz bu filtreye uygun fatura hazırlığı yok. Sipariş detayındaki fatura önizleme ekranından yerel hazırlık oluşturabilirsiniz.</p>
  @endforelse
  {{ $rows->links() }}
</section>
@endsection
