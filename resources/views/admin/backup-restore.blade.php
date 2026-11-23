@extends('admin.layout')
@section('title', 'Yedekleme ve Geri Yükleme | NEWTIC')
@section('topbar', 'Yedekleme ve Geri Yükleme')
@section('content')
<style>
.restore-page {max-width:1000px;margin:0 auto;display:grid;gap:18px}
.restore-page h1{font-family:'Plus Jakarta Sans',sans-serif;font-size:clamp(23px,3vw,30px);color:#092037;margin:0}
.restore-page h2{font-size:18px;margin:0;color:#092037}
.restore-page p{line-height:1.65;margin:8px 0 0;color:#516074}
.restore-surface{background:#fff;border:1px solid #dde5ee;border-radius:15px;padding:clamp(16px,3vw,27px)}
.restore-intro{display:flex;align-items:flex-start;justify-content:space-between;gap:12px}
.restore-chip{display:inline-block;color:#092037;background:#edf2fa;border-radius:20px;font-size:12px;padding:6px 11px;white-space:nowrap}
.restore-fields{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin:20px 0}
.restore-field{display:block;border:1px solid #dce4ee;border-radius:12px;padding:16px;min-width:0}
.restore-field strong{display:block;font-size:14px;color:#092037;margin-bottom:5px}
.restore-field small{display:block;color:#63718a;margin:0 0 11px}
.restore-field input{width:100%;max-width:100%;color:#092037}
.restore-button{background:#092037;color:#fff;border:0;border-radius:10px;padding:12px 19px;font-weight:600;cursor:pointer;font-family:inherit;font-size:14px;display:inline-flex;align-items:center;justify-content:center;text-decoration:none}
.restore-button:hover{background:#183f61;color:#fff}
.restore-button.secondary{background:#eef2f8;color:#092037}
.restore-button.danger{background:#9e2433;color:white}
.restore-list{display:grid;gap:12px;margin-top:15px}
.restore-entry{border:1px solid #e3e9f1;border-radius:12px;padding:15px;display:grid;gap:12px}
.restore-entry-head{display:flex;gap:12px;justify-content:space-between;flex-wrap:wrap}
.restore-entry small{color:#63718a}
.restore-entry strong{color:#092037}
.restore-status{font-size:12px;background:#edf2f8;color:#24374d;padding:5px 9px;border-radius:16px;font-weight:600}
.restore-status.done{background:#e8f6eb;color:#1c692f}
.restore-status.failed{background:#ffebed;color:#8b1f32}
.restore-status.running,.restore-status.queued{background:#fff2de;color:#86601d}
.restore-confirm{background:#f8fafc;padding:15px;border-radius:10px;display:grid;gap:11px}
.restore-confirm input[type=text]{border:1px solid #bac7d8;border-radius:8px;padding:11px 12px;max-width:230px;width:100%;background:#fff;color:#092037}
.restore-confirm label{display:flex;gap:8px;align-items:flex-start;color:#35465b;font-size:13px}
.restore-warning{background:#fff8ed;border:1px solid #f4dfb4;padding:14px;border-radius:10px;color:#694d1f;font-size:13px;line-height:1.6}
@media(max-width:700px){.restore-fields{grid-template-columns:1fr}.restore-intro{display:block}.restore-chip{margin-top:8px}}
</style>
<div class="restore-page">
  <section class="restore-surface">
    <div class="restore-intro">
      <div>
        <h1>Yedekleme ve Geri Yükleme</h1>
        <p>Günlük yedekleri hosting sağlayıcınız yönetir. Buradan daha önce indirdiğiniz Hostinger yedeklerini geri yükleyebilirsiniz.</p>
      </div>
      <span class="restore-chip">Harici yedekleme</span>
    </div>
    <p>Site dosyaları ve veritabanı Hostinger'dan ayrı indirilir. Tam geri yükleme için aynı tarihe ait iki dosyayı birlikte seçin.</p>
    <form method="POST" action="{{ route('admin.backup-restore.upload') }}" enctype="multipart/form-data">
      @csrf
      <div class="restore-fields">
        <label class="restore-field">
          <strong>1. Veritabanı yedeği</strong>
          <small>Hostinger'dan indirilen .sql.gz</small>
          <input type="file" name="database" accept=".sql.gz,.gz">
        </label>
        <label class="restore-field">
          <strong>2. Site dosyaları</strong>
          <small>Hostinger'dan indirilen .tar.gz</small>
          <input type="file" name="files" accept=".tar.gz,.gz">
        </label>
      </div>
      <button type="submit" class="restore-button">Dosyaları yükle ve kontrol et</button>
    </form>
    <p style="font-size:12px">Güvenlik nedeniyle yedek içindeki PHP kodları, .env dosyası ve sunucu ayarları otomatik değiştirilmez. GitHub'daki NEWTIC yazılımı korunur. Dosya başına üst sınır 256 MB'dir; hosting PHP yükleme sınırı daha düşük olabilir.</p>
  </section>

  <section class="restore-surface">
    <h2>Geri yükleme işlemleri</h2>
    <p>Yedekler doğrudan uygulanmaz. Önce kontrol edilir, ardından sizin onayınızla cron üzerinden çalışır.</p>
    <div class="restore-list">
      @forelse($jobs as $job)
      @php
        $names = ['ready' => 'Onay bekliyor', 'queued' => 'Sırada', 'running' => 'Geri yükleniyor', 'done' => 'Tamamlandı', 'failed' => 'Hata'];
        $status = $job['status'] ?? 'failed';
      @endphp
      <div class="restore-entry">
        <div class="restore-entry-head">
          <div><strong>{{ \Carbon\Carbon::parse($job['created_at'] ?? 'now')->format('d.m.Y H:i') }} tarihli yükleme</strong>
            <div><small>{{ !empty($job['database']) ? 'Veritabanı ' : '' }}{{ !empty($job['media']) ? ' · Medya (' . (int)($job['images'] ?? 0) . ' dosya)' : '' }}</small></div>
          </div>
          <span class="restore-status {{ $status }}">{{ $names[$status] ?? 'Bilinmiyor' }}</span>
        </div>
        <small>{{ $job['message'] ?? '' }}</small>
        @if($status === 'ready')
          @if(request('restore') === $job['id'])
          <form class="restore-confirm" method="POST" action="{{ route('admin.backup-restore.confirm', $job['id']) }}">
            @csrf
            <div class="restore-warning"><strong>Dikkat:</strong> Bu işlem mevcut ürün, sipariş ve müşteri verilerini eski yedekteki durumuna döndürebilir. İşlemden önce Hostinger'da bugünkü verilerin güncel bir kurtarma yedeği bulunduğunu doğrulayın. Eski yedek, yeni NEWTIC sürümüyle uyumsuz olabilir.</div>
            <label><input type="checkbox" name="safety_copy" value="1" required> Güncel kurtarma yedeğinin mevcut olduğunu doğruladım.</label>
            <label for="restore-confirm-{{ $job['id'] }}">Onaylamak için GERI YUKLE yazın:</label>
            <input id="restore-confirm-{{ $job['id'] }}" type="text" name="confirmation" autocomplete="off" required placeholder="GERI YUKLE">
            <div><button type="submit" class="restore-button danger">Geri yüklemeyi onayla</button></div>
          </form>
          @else
            <div><a class="restore-button secondary" href="{{ route('admin.backup-restore.index', ['restore' => $job['id']]) }}">İncele ve onayla</a></div>
          @endif
        @endif
        @if(in_array($status, ['ready', 'failed', 'done'], true))
        <form method="POST" action="{{ route('admin.backup-restore.delete', $job['id']) }}" onsubmit="return confirm('Geçici yüklenen dosyalar silinsin mi?')">
          @csrf @method('DELETE')
          <button type="submit" class="restore-button secondary">Geçici dosyaları sil</button>
        </form>
        @endif
      </div>
      @empty
      <p>Henüz yüklenmiş bir yedek bulunmuyor.</p>
      @endforelse
    </div>
  </section>
  <section class="restore-surface">
    <h2>ZIP indirmek istiyorum</h2>
    <p>Günlük yedekleriniz zaten Hostinger'da tutuluyor. Komple arşivi Hostinger panelinden indirip bilgisayarınızda veya farklı bir bulutta saklayabilirsiniz. NEWTIC bu nedenle ikinci bir otomatik yedek arşivi üretmez.</p>
    <p><a href="https://hpanel.hostinger.com/websites" target="_blank" rel="noopener noreferrer">Hostinger yedeklerine git</a></p>
  </section>
</div>
@endsection
