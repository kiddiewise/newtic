@extends('admin.layout')
@section('title', 'Sistem Sağlığı | NEWTIC')
@section('topbar', 'Sistem Sağlığı')
@section('content')
@php
  $statusText = ['ok' => 'Çalışıyor', 'warning' => 'İnceleme gerekli', 'unknown' => 'Doğrulanmadı', 'disabled' => 'Devre dışı'];
  $statusIcons = ['ok' => 'check', 'warning' => 'alert', 'unknown' => 'info', 'disabled' => 'clock'];
  $destinations = [
    'paytr' => ['route' => 'admin.paytr-settings.edit', 'title' => 'PAYTR ayarları'],
    'shipping' => ['route' => 'admin.shipping-operations.index', 'title' => 'Kargo operasyonları'],
  ];
@endphp
<div class="health-page">
  <div class="health-hero">
    <div>
      <p class="health-kicker">SİSTEM YÖNETİMİ / GÖZLEMLEME</p>
      <h1>Sistem Sağlığı</h1>
      <p>Ödemeler, zamanlanmış görevler, yedekler ve sunucu durumunu tek ekranda izle. Bilinmeyen durumlar başarı olarak gösterilmez.</p>
    </div>
    <div class="health-hero-actions">
      <span class="health-checked-at"><x-admin-icon name="clock" /> Kontrol: {{ $checked_at->format('d.m.Y H:i:s') }}</span>
      <a href="{{ route('admin.system-health') }}" class="health-refresh"><x-admin-icon name="refresh-cw" /> Yenile</a>
    </div>
  </div>

  <div class="health-overview" aria-label="Sistem sağlık durumları özeti">
    <div><span class="health-overview-dot is-ok"></span><strong>{{ $counts['ok'] }}</strong><small>Çalışıyor</small></div>
    <div><span class="health-overview-dot is-warning"></span><strong>{{ $counts['warning'] }}</strong><small>İnceleme gerekli</small></div>
    <div><span class="health-overview-dot is-unknown"></span><strong>{{ $counts['unknown'] }}</strong><small>Doğrulanmadı</small></div>
    <div><span class="health-overview-dot is-disabled"></span><strong>{{ $counts['disabled'] }}</strong><small>Devre dışı</small></div>
  </div>

  <div class="health-section-head">
    <div><h2>Operasyonların durumu</h2><p>Yalnızca mevcut veritabanı ve sunucu gözlemleri kullanılır. Harici hizmetlere test isteği gönderilmez.</p></div>
  </div>
  <div class="health-check-grid">
    @foreach($checks as $check)
      <section class="health-check-card is-{{ $check['status'] }}" aria-labelledby="health-label-{{ $check['key'] }}">
        <div class="health-check-head">
          <span class="health-check-symbol"><x-admin-icon :name="$statusIcons[$check['status']]" /></span>
          <span class="health-status-pill is-{{ $check['status'] }}">{{ $statusText[$check['status']] }}</span>
        </div>
        <h3 id="health-label-{{ $check['key'] }}">{{ $check['label'] }}</h3>
        <p class="health-check-summary">{{ $check['summary'] }}</p>
        @if($check['hint'])
          <p class="health-check-hint">{{ $check['hint'] }}</p>
        @endif
        <div class="health-check-foot">
          <span>
            <x-admin-icon name="clock" />
            @if($check['last_at'] instanceof \DateTimeInterface)
              {{ $check['last_at']->format('d.m.Y H:i') }}
            @elseif(is_string($check['last_at']) && $check['last_at'] !== '')
              {{ mb_substr($check['last_at'], 0, 26) }}
            @else
              Henüz tarih yok
            @endif
          </span>
          @if(isset($destinations[$check['key']]))
            <a href="{{ route($destinations[$check['key']]['route']) }}">{{ $destinations[$check['key']]['title'] }} <x-admin-icon name="arrow-right" /></a>
          @endif
        </div>
      </section>
    @endforeach
  </div>

  <div class="health-lower-grid">
    <section class="health-surface">
      <div class="health-surface-head"><div><h2>Cron görevleri</h2><p>Komutların en son çalışma kayıtları. Zamanlayıcı için <code>php artisan schedule:run</code> gerekir.</p></div></div>
      <div class="health-task-scroll">
        <table class="health-task-table">
          <thead><tr><th scope="col">Görev</th><th scope="col">Sıklık</th><th scope="col">Son çalıştırma</th><th scope="col">Durum</th></tr></thead>
          <tbody>
            @foreach($tasks as $task)
              <tr>
                <td><strong>{{ $task['label'] }}</strong><small>{{ $task['detail'] }}</small></td>
                <td>{{ $task['cadence'] }}</td>
                <td>{{ $task['last_at']?->format('d.m.Y H:i') ?? 'Kayıt yok' }}</td>
                <td><span class="health-task-status is-{{ $task['status'] }}">{{ $statusText[$task['status']] }}</span></td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>
    <div class="health-stack">
      <section class="health-surface">
        <div class="health-surface-head"><div><h2>Uygulama sürümü</h2><p>Yayın paketi ve çalışma ortamı</p></div></div>
        <div class="health-revision"><x-admin-icon name="package" /><div><small>NEWTIC sürüm kaydı</small><strong>{{ $version }}</strong></div></div>
        <dl class="health-runtime">
          <div><dt>Laravel</dt><dd>{{ $runtime['laravel'] }}</dd></div>
          <div><dt>PHP</dt><dd>{{ $runtime['php'] }}</dd></div>
          <div><dt>Queue sürücüsü</dt><dd>{{ $runtime['queue'] }}</dd></div>
          <div><dt>Önbellek sürücüsü</dt><dd>{{ $runtime['cache'] }}</dd></div>
        </dl>
      </section>
      <section class="health-surface">
        <div class="health-surface-head"><div><h2>Son hata kayıtları</h2><p>Güvenlik nedeniyle yalnızca tarih ve hata seviyesi gösterilir. İstek içerikleri ve stack trace saklanmaz.</p></div></div>
        @forelse($logs as $log)
          <div class="health-log-row"><span class="health-log-level">{{ $log['level'] }}</span><time>{{ $log['at'] }}</time></div>
        @empty
          <p class="health-no-logs">Okunabilen yerel log kesitinde hata kaydı bulunamadı. Uzak log servisleri bu listeye dahil değildir.</p>
        @endforelse
      </section>
    </div>
  </div>

  <section class="health-surface health-security" id="guvenlik" aria-labelledby="health-security-title">
    <div class="health-surface-head"><div><h2 id="health-security-title">Güvenlik Kontrolü</h2><p>Yalnızca güvenli yapılandırma göstergeleri gösterilir. Anahtarlar, şifreler ve oturum verileri hiçbir zaman ekrana gönderilmez.</p></div></div>
    <div class="health-security-grid">
      @foreach($security as $check)
        <div class="health-security-item">
          <span class="health-status-pill is-{{ $check['status'] }}">{{ $statusText[$check['status']] }}</span>
          <strong>{{ $check['label'] }}</strong>
          <p>{{ $check['detail'] }}</p>
        </div>
      @endforeach
    </div>
  </section>

  <div class="health-footnote">
    <x-admin-icon name="info" />
    <div><strong>Hosting bağımsız mimari</strong><p>NEWTIC; Hostinger, başka bir paylaşımlı hosting, VPS veya bulut sunucusuna özgü API gerektirmez. Yedekleme başarı bilgisi, gerçek yedekleme işlemi tamamlandıktan sonra <code>php artisan health:backup-report --result=success --provider=external</code> komutuyla sunulmalıdır. Bu komut yedek oluşturmaz. Bir işin çalışmış olması, harici hizmetin de başarılı olduğu anlamına gelmez.</p></div>
  </div>
</div>
@endsection
