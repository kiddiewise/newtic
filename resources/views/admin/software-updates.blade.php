@extends('admin.layout')
@section('title', 'Yazılım Güncellemeleri | NEWTIC')
@section('topbar', 'Yazılım Güncellemeleri')
@section('content')
@php
  $busy = in_array($updateState['status'] ?? 'idle', ['queued', 'running'], true);
  $stateLabels = ['idle'=>'Bekliyor','queued'=>'Sırada','running'=>'Güncelleniyor','done'=>'Tamamlandı','failed'=>'Kontrol gerekli'];
@endphp
<style>
.nu-page{display:grid;gap:18px;max-width:980px;margin:0 auto}
.nu-card{background:#fff;border:1px solid #dee5ef;border-radius:18px;padding:clamp(18px,3vw,28px)}
.nu-page h1,.nu-page h2{font-family:'Plus Jakarta Sans',sans-serif;color:#092037;letter-spacing:-.03em}
.nu-page h1{font-size:clamp(24px,3vw,30px);margin:0 0 5px}
.nu-page h2{font-size:19px;margin:0 0 5px}
.nu-page p{color:#536276;line-height:1.6;margin:7px 0}
.nu-heading{display:flex;justify-content:space-between;gap:15px;flex-wrap:wrap;align-items:center}
.nu-label{border-radius:20px;padding:7px 12px;font-size:12px;background:#edf1f6;color:#092037;font-weight:600}
.nu-version{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:19px}
.nu-version div{background:#f3f6fa;padding:16px;border-radius:13px;display:grid;gap:6px}
.nu-version span{font-size:12px;color:#627185}
.nu-version strong{color:#092037;font-size:25px;font-family:'Plus Jakarta Sans',sans-serif}
.nu-update-alert{border:1px solid #cbd8ec;border-left:4px solid #092037;background:#f5f8fc;padding:16px;border-radius:13px}
.nu-btn{border:0;border-radius:10px;padding:12px 19px;background:#092037;color:#fff;font:600 14px Montserrat,sans-serif;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.nu-btn:hover{background:#254362;color:#fff}
.nu-btn:disabled{opacity:.5;cursor:not-allowed}
.nu-btn.secondary{background:#edf2f8;color:#092037}
.nu-btn-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
.nu-confirm{margin-top:17px;display:grid;gap:13px;background:#f8fafc;border:1px solid #e0e8f2;border-radius:13px;padding:16px}
.nu-confirm label{display:flex;gap:10px;align-items:flex-start;font-size:13px;color:#405166;line-height:1.6}
.nu-confirm input[type="text"]{max-width:220px;width:100%;border:1px solid #c5cfdc;border-radius:9px;padding:10px;background:#fff;color:#092037}
.nu-warning{background:#fff9ed;border:1px solid #f0dfb8;border-radius:10px;color:#705125;padding:13px;font-size:13px;line-height:1.55}
.nu-note{white-space:pre-line;max-height:245px;overflow:auto;background:#f7f9fc;border-radius:12px;padding:16px;font-size:13px;line-height:1.8;color:#405064;margin:12px 0}
.nu-state{font-size:13px;margin-top:14px;padding:14px;border:1px solid #e0e6ef;border-radius:12px;background:#f8fafc;color:#283e56}
@media(max-width:550px){.nu-version{grid-template-columns:1fr}}
</style>
<div class="nu-page">
  <section class="nu-card">
    <div class="nu-heading">
      <div>
        <h1>NEWTIC Yazılım Güncellemeleri</h1>
        <p>Mağaza verilerin değişmeden yazılımın yeni sürümlerini kullan.</p>
      </div>
      <span class="nu-label">{{ $hasUpdate ? 'Yeni sürüm mevcut' : 'Sürüm kontrolü' }}</span>
    </div>
    <div class="nu-version">
      <div><span>Kurulu sürüm</span><strong>{{ $currentVersion === '0.0.0' ? 'Geliştirme sürümü' : $currentVersion }}</strong></div>
      <div><span>Yayımlanan son sürüm</span><strong>{{ $latestRelease['version'] ?? 'Henüz yok' }}</strong></div>
    </div>
    <div class="nu-btn-row">
      <form method="POST" action="{{ route('admin.software-updates.check') }}">
        @csrf
        <button class="nu-btn secondary" type="submit" @disabled($busy)><x-admin-icon name="refresh-cw" /> Güncellemeleri kontrol et</button>
      </form>
      @if($hasUpdate && !$busy)
        <a class="nu-btn" href="#guncelle"><x-admin-icon name="download" /> Güncellemeyi yükle</a>
      @endif
    </div>
    @if($latestRelease && !empty($latestRelease['checked_at']))
      <p style="font-size:12px">Son kontrol: {{ \Carbon\Carbon::parse($latestRelease['checked_at'])->format('d.m.Y H:i') }}</p>
    @else
      <p style="font-size:12px">İlk kontrol cron tarafından otomatik yapılacak. İstersen manuel kontrol başlatabilirsin.</p>
    @endif
    @if(($updateState['status'] ?? 'idle') !== 'idle')
      <div class="nu-state" role="status">
        <strong>{{ $stateLabels[$updateState['status'] ?? 'idle'] ?? 'Bilinmiyor' }}</strong>
        <p>{{ $updateState['message'] ?? '' }}</p>
        @if($busy)
          <p>Güncelleme sırasında mağazada kısa süreli bakım ekranı görünebilir. Bu ekranı açık tutmak zorunda değilsiniz.</p>
        @endif
      </div>
    @endif
  </section>

  @if($hasUpdate && !$busy && $latestRelease)
    <section class="nu-card" id="guncelle">
      <div class="nu-update-alert">
        <h2>NEWTIC {{ $latestRelease['version'] }} hazır</h2>
        <p>Resmî sürüm paketi GitHub üzerinden kontrol edilerek kurulacak.</p>
      </div>
      @if(!empty($latestRelease['notes']))
        <h2 style="margin-top:20px">Bu sürümde neler var?</h2>
        <div class="nu-note">{{ $latestRelease['notes'] }}</div>
      @endif
      <form class="nu-confirm" method="POST" action="{{ route('admin.software-updates.install') }}">
        @csrf
        <div class="nu-warning"><strong>Güncelleme öncesi:</strong> Hostinger'da güncel bir site ve veritabanı yedeği bulunduğundan emin olun. Güncelleme mevcut sipariş, ürün ve müşteri kayıtlarını silmez; ancak yazılımın gerektirdiği veritabanı değişiklikleri uygulanabilir. Sorun halinde yedekten geri dönmek gerekebilir.</div>
        <label>
          <input type="checkbox" name="backup_confirmed" value="1" required>
          Hostinger üzerinde güncel ve geri yüklenebilir yedeğim olduğunu doğruladım.
        </label>
        <label for="newtic-update-confirm">Kurulumu onaylamak için GUNCELLE yazın:</label>
        <input type="text" id="newtic-update-confirm" name="confirmation" placeholder="GUNCELLE" autocomplete="off" required>
        <div><button type="submit" class="nu-btn"><x-admin-icon name="download" /> NEWTIC {{ $latestRelease['version'] }} sürümüne güncelle</button></div>
      </form>
    </section>
  @elseif(!$hasUpdate)
    <section class="nu-card">
      <h2>Güncellemeler</h2>
      <p>Yeni bir resmî sürüm yayımlandığında burada ve yönetim ana sayfanda bildirim görünecek. Güncellemeler kendi kendine kurulmaz; her zaman sen onay verirsin.</p>
    </section>
  @endif

  <section class="nu-card">
    <h2>Mağazam korunacak mı?</h2>
    <p>Güncelleme paketleri yalnızca resmî NEWTIC yazılım dosyalarını yeniler. Mağazana özel <code>.env</code>, ürün fotoğrafları, özel yüklemeler ve ödeme bağlantı bilgileri korunur. Veritabanında sürümün gerektirdiği güvenli migration işlemleri çalışabilir.</p>
    <p style="font-size:12px">Bu özellik güncelleme kaynağı olarak yalnızca NEWTIC'in resmî GitHub sürüm kanalını kullanır. Geliştirici dalındaki değişiklikler müşterilere sunulmaz.</p>
  </section>
</div>
@endsection
