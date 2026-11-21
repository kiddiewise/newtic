@extends('admin.layout')
@section('title', 'Pazarlama ve Analitik Merkezi | NEWTIC')
@section('content')
<div class="analytics-center" data-analytics-dashboard data-live-url="{{ route('admin.analytics.live') }}">
  <div class="admin-page-heading admin-heading-actions">
    <div>
      <p class="admin-eyebrow">NEWTIC INTELLIGENCE / VERİ MERKEZİ</p>
      <h1>Pazarlama ve Analitik</h1>
      <p>Ziyaretleri, ürün ilgisini ve doğrulanmış satışları tek bir panelden incele.</p>
    </div>
    <div class="analytics-actions">
      <nav class="analytics-periods" aria-label="Analiz dönemi">
        @foreach([7 => '7 gün', 30 => '30 gün', 90 => '90 gün'] as $period => $label)
          <a @class(['is-active' => $days === $period]) href="{{ route('admin.analytics', ['days' => $period]) }}"
             @if($days === $period) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
      </nav>
      <a class="analytics-export-button" href="{{ route('admin.analytics.export', ['days' => $days]) }}"><x-admin-icon name="download"/> CSV indir</a>
    </div>
  </div>

  <div class="analytics-privacy-notice">
    <x-admin-icon name="shield"/>
    <span><strong>Gizlilik odaklı ölçüm:</strong> Davranışlar yalnızca analitik izni veren ziyaretçilerden toplanır. IP adresi, müşteri kimliği ve tam konum saklanmaz. Canlı sayaç tüm ziyaretçileri değil, izne dayalı etkin anonim oturumları gösterir. Test siparişleri ciroya girmez.</span>
  </div>

  <section class="analytics-summary" aria-label="Temel göstergeler">
    @php $comparisonKeys = ['visitors', 'pageViews', 'revenueCents', 'orderCount', 'averageCents', null]; @endphp
    @foreach([
      ['Ziyaretçi oturumu', number_format($visitors, 0, ',', '.'), 'Analitik izni bulunan farklı oturumlar'],
      ['Sayfa görüntüleme', number_format($pageViews, 0, ',', '.'), 'İzinli ziyaretlerin görüntülemeleri'],
      ['Doğrulanmış ciro', number_format($revenueCents / 100, 2, ',', '.').' ₺', 'Banka veya PAYTR onaylı gerçek siparişler'],
      ['Gerçek satış adedi', number_format($orderCount, 0, ',', '.'), 'Test ve iptal siparişleri hariç'],
      ['Ortalama sipariş', number_format($averageCents / 100, 2, ',', '.').' ₺', 'Gerçek doğrulanmış siparişlerden'],
      ['Şu an aktif', (string)$live['online'], 'Son 5 dakikadaki izinli oturumlar'],
    ] as $index => $item)
      <article class="analytics-kpi {{ $index === 2 ? 'is-featured' : '' }}">
        <span>{{ $item[0] }}</span>
        <strong>{{ $item[1] }}</strong>
        <small>{{ $item[2] }}</small>
        @if($comparisonKeys[$index])
          @php $delta = $comparison[$comparisonKeys[$index]]; @endphp
          <div @class(['analytics-delta','is-up' => $delta['percent'] !== null && $delta['percent'] > 0,
            'is-down' => $delta['percent'] !== null && $delta['percent'] < 0])>
            @if($delta['percent'] === null)
              <span>Önceki dönemde veri yok</span>
            @else
              <span>{{ $delta['percent'] > 0 ? '+' : '' }}{{ number_format($delta['percent'], 1, ',', '.') }}%</span>
              <small>önceki {{ $days }} güne göre</small>
            @endif
          </div>
        @endif
      </article>
    @endforeach
  </section>

  <div class="analytics-section-grid">
    <section class="analytics-card analytics-span-2">
      <div class="analytics-card-heading">
        <div><p class="analytics-eyebrow">TRAFFIC INTELLIGENCE</p><h2>Ziyaretçi ve sayfa görüntüleme trendi</h2></div>
        <div class="analytics-legend"><span><i class="analytics-dot is-views"></i> Sayfa görüntüleme</span><span><i class="analytics-dot is-visitors"></i> Oturum</span></div>
      </div>
      <div class="analytics-plot" role="img" aria-label="Günlere göre sayfa görüntüleme ve anonim ziyaretçi oturumu grafiği">
        <svg data-analytics-line-chart viewBox="0 0 720 245" preserveAspectRatio="none" aria-hidden="true"></svg>
      </div>
      <script type="application/json" data-analytics-trend>@json($trend)</script>
      <div class="analytics-trend-foot">Veriler analitik izninin verilmesinden itibaren oluşur. Aynı kişi farklı oturumlarda birden fazla sayılabilir.</div>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">CONVERSION FUNNEL</p><h2>Alışveriş yolculuğu</h2></div></div>
      <p class="analytics-note">Aşağıdaki oranlar izinli oturumlardan hesaplanır. Ödeme onayları ayrıca güvenilir sipariş kayıtlarından gelir.</p>
      <div class="analytics-funnel">
        @foreach($funnel as $step)
          <div class="analytics-funnel-step">
            <div><span>{{ $step['label'] }}</span><strong>{{ number_format($step['total'], 0, ',', '.') }}</strong></div>
            <div class="analytics-funnel-track"><span style="width:{{ $visitors ? min(100, round(($step['total'] / $visitors) * 100, 1)) : 0 }}%"></span></div>
          </div>
        @endforeach
      </div>
      <div class="analytics-inline-stats">
        <div><span>Sepete ekleme olayı</span><strong>{{ number_format($cartAdds, 0, ',', '.') }}</strong></div>
        <div><span>Ödeme başlangıcı</span><strong>{{ number_format($checkoutStarts, 0, ',', '.') }}</strong></div>
      </div>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">SALES VELOCITY</p><h2>Doğrulanmış günlük ciro</h2></div></div>
      @php $maxRevenue = max(1, collect($trend)->max('revenue')); @endphp
      <div class="analytics-revenue-chart" role="img" aria-label="Günlere göre doğrulanmış sipariş cirosu, TL">
        @foreach($trend as $day)
          <div class="analytics-revenue-day" title="{{ $day['label'] }}: {{ number_format($day['revenue'], 2, ',', '.') }} ₺"
               aria-label="{{ $day['label'] }} {{ number_format($day['revenue'], 2, ',', '.') }} TL" tabindex="0">
            <span class="analytics-revenue-bar" style="height:{{ $day['revenue'] ? max(5, 100 * $day['revenue'] / $maxRevenue) : 1 }}%"></span>
            @if($days === 7 || $loop->iteration % max(1, (int)floor($days / 7)) === 0)
              <small>{{ $day['date'] }}</small>
            @endif
          </div>
        @endforeach
      </div>
      <p class="analytics-note">Satışlar siparişin oluşturulduğu güne göre gösterilir. İadeler bu ilk sürümün net ciro hesabına henüz dahil değildir.</p>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading">
        <div><p class="analytics-eyebrow">CUSTOMER RETENTION</p><h2>Müşteri sadakati</h2></div>
        <span class="analytics-chip">Üyelikli satışlar</span>
      </div>
      <div class="analytics-customer-metric">
        <strong>{{ $buyers['returning_percent'] === null ? '—' : number_format($buyers['returning_percent'], 1, ',', '.').'%' }}</strong>
        <span>Bu dönemde alışveriş yapan üyeler arasında daha önce de gerçek satın alma yapmış olanların oranı</span>
      </div>
      <div class="analytics-device-track analytics-retention-track">
        <span style="width:{{ $buyers['returning_percent'] ?? 0 }}%"></span>
      </div>
      <div class="analytics-inline-stats analytics-retention-stats">
        <div><span>İlk kez satın alan üyeler</span><strong>{{ number_format($buyers['new'], 0, ',', '.') }}</strong></div>
        <div><span>Geri dönen üyeler</span><strong>{{ number_format($buyers['returning'], 0, ',', '.') }}</strong></div>
      </div>
      <p class="analytics-note">{{ number_format($buyers['all'], 0, ',', '.') }} üyelikli alıcı incelendi. Misafir alışverişleri kişisel e-posta ile eşleştirilmez; bu oran tüm müşterilerin tekrar satın alma oranı değildir.</p>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading">
        <div><p class="analytics-eyebrow">OPPORTUNITY RADAR</p><h2>Satış fırsatı göstergeleri</h2></div>
        <span class="analytics-chip">Kurala dayalı</span>
      </div>
      <p class="analytics-note">En az 10 izinli görüntülenmesi olan ürünlerde düşük sepete ekleme oranı veya tükenen stok tespit edilir.</p>
      <div class="analytics-opportunities">
        @forelse($opportunities as $opportunity)
          <div class="analytics-opportunity">
            <div>
              <a href="{{ route('admin.products.edit', $opportunity['id']) }}">{{ $opportunity['name'] }}</a>
              <span>{{ $opportunity['reason'] }}</span>
            </div>
            <small>{{ $opportunity['views'] }} inceleme · {{ $opportunity['cart_adds'] }} sepete ekleme</small>
          </div>
        @empty
          <p class="analytics-empty">Henüz yeterli veriyle oluşan fırsat uyarısı bulunmuyor. Bu, sorun olmadığı anlamına gelmez.</p>
        @endforelse
      </div>
      <p class="analytics-note">Bu sinyaller kesin sonuç veya nedensellik kanıtı değildir; ürün ve stok incelemesi için başlangıç noktasıdır.</p>
    </section>

    <section class="analytics-card analytics-span-2">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">PRODUCT INTELLIGENCE</p><h2>En çok ilgi gören ürünler</h2></div><span class="analytics-chip">Ürün bazında</span></div>
      <div class="analytics-table-wrap">
        <table class="analytics-table">
          <thead><tr><th>Ürün</th><th class="analytics-number">İnceleme</th><th class="analytics-number">Sepete ekleme</th><th class="analytics-number">Satılan adet</th><th class="analytics-number">Doğrulanmış ürün cirosu</th></tr></thead>
          <tbody>
          @forelse($products as $productRow)
            <tr>
              <td><a href="{{ route('admin.products.edit', $productRow['id']) }}">{{ $productRow['name'] }}</a></td>
              <td class="analytics-number">{{ number_format($productRow['views'], 0, ',', '.') }}</td>
              <td class="analytics-number">{{ number_format($productRow['cart_adds'], 0, ',', '.') }}</td>
              <td class="analytics-number">{{ number_format($productRow['sold'], 0, ',', '.') }}</td>
              <td class="analytics-number"><strong>{{ number_format($productRow['revenue_cents'] / 100, 2, ',', '.') }} ₺</strong></td>
            </tr>
          @empty <tr><td colspan="5" class="analytics-empty">Bu dönemde henüz ürün performansı verisi yok. Analitik izni verilen yeni ziyaretlerle veriler birikmeye başlayacak.</td></tr>
          @endforelse
          </tbody>
        </table>
      </div>
      <p class="analytics-note">Gramajları ayrı SKU olan ürünler burada da ayrı gösterilir. Görüntüleme ve sepete ekleme değerleri izinli ziyaretlerdir; satış adetleri ödeme kayıtlarından hesaplanır.</p>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">SEARCH INSIGHTS</p><h2>Müşteriler ne arıyor?</h2></div></div>
      <div class="analytics-mini-table">
        @forelse($searches as $search)
          <div><span>{{ $search->search_term }}</span><strong>{{ $search->searches }} arama</strong>
            @if($search->empty_results > 0)<small class="analytics-alert">{{ $search->empty_results }} sonuçsuz</small>@endif</div>
        @empty <p class="analytics-empty">Henüz arama verisi yok.</p> @endforelse
      </div>
      <p class="analytics-note">E-posta, telefon ve URL olabilecek arama terimleri kaydedilmez.</p>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">CONTENT PERFORMANCE</p><h2>En çok ziyaret edilen sayfalar</h2></div></div>
      <div class="analytics-mini-table">
        @forelse($pages as $page)
          <div><span>{{ $page->page_path }}</span><strong>{{ $page->views }} görüntüleme</strong></div>
        @empty <p class="analytics-empty">Henüz sayfa ziyareti yok.</p> @endforelse
      </div>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">ACQUISITION</p><h2>Trafik kaynakları</h2></div></div>
      <div class="analytics-mini-table">
        @forelse($sources as $source)
          <div><span>{{ $source->source }}</span><strong>{{ $source->total }} gösterim</strong></div>
        @empty <p class="analytics-empty">Henüz trafik kaynağı verisi yok.</p> @endforelse
      </div>
      <p class="analytics-note">Yalnızca yönlendiren alan adı veya izinli UTM kaynağı saklanır; URL parametreleri ve kişisel veriler kaydedilmez.</p>
    </section>

    <section class="analytics-card">
      <div class="analytics-card-heading"><div><p class="analytics-eyebrow">DEVICE MIX</p><h2>Cihaz dağılımı</h2></div></div>
      @php $deviceTotal = max(1, array_sum($devices->all())); @endphp
      @foreach(['mobile' => 'Mobil', 'desktop' => 'Masaüstü', 'tablet' => 'Tablet'] as $deviceKey => $deviceLabel)
        <div class="analytics-device-row">
          <div><span>{{ $deviceLabel }}</span><strong>{{ number_format((int)($devices[$deviceKey] ?? 0), 0, ',', '.') }}</strong></div>
          <div class="analytics-device-track"><span style="width:{{ round(100 * (int)($devices[$deviceKey] ?? 0) / $deviceTotal, 1) }}%"></span></div>
        </div>
      @endforeach
      <p class="analytics-note">Cihaz sınıflaması tarayıcı bilgisine dayanır; yaklaşık bir ayrımdır.</p>
    </section>

    <section class="analytics-card analytics-span-2" data-live-section>
      <div class="analytics-card-heading">
        <div><p class="analytics-eyebrow"><span class="analytics-live-indicator"></span> LIVE ACTIVITY</p><h2>Şu anda sitede</h2></div>
        <div class="analytics-live-summary"><strong data-live-count>{{ $live['online'] }}</strong><span>aktif anonim oturum</span></div>
      </div>
      <p class="analytics-note">Son 5 dakikada etkinlik gösteren ve analitik izni vermiş oturumlar. Tablo 20 saniyede bir yenilenir. Kullanıcıların kimliği veya kesin konumu gösterilmez.</p>
      <p class="analytics-live-timestamp">Son kontrol: <span data-live-updated>{{ $live['updated_at'] }}</span></p>
      <div class="analytics-table-wrap">
        <table class="analytics-table">
          <thead><tr><th>Son görüntülenen sayfa</th><th>Cihaz</th><th>Yaklaşık şehir/ülke</th><th>Son etkinlik</th></tr></thead>
          <tbody data-live-rows>
          @forelse($live['visitors'] as $visitor)
            <tr><td>{{ $visitor['page'] }}</td><td>{{ $visitor['device'] }}</td><td>{{ $visitor['location'] }}</td><td>{{ $visitor['seconds_ago'] }} sn önce</td></tr>
          @empty <tr><td colspan="4" class="analytics-empty">Şu anda izne dayalı aktif oturum bulunmuyor.</td></tr> @endforelse
          </tbody>
        </table>
      </div>
      <p class="analytics-note">Konum alanı için doğrulanmış, gizlilik uyumlu bir yerel GeoIP kaynağı yapılandırılmadığından konumlar “Bilinmiyor” gösterilebilir. GPS ve kesin adres izlenmez.</p>
    </section>
  </div>
</div>
@endsection
