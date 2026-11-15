@extends('admin.layout')
@section('title','Kampanya E-postaları | NEWTIC')
@section('topbar','Kampanya E-postaları')
@section('content')
<div class="admin-page-heading admin-heading-actions">
 <div><p class="admin-eyebrow">MÜŞTERİ İLETİŞİMİ / KAMPANYALAR</p><h1>Kampanya e-postaları</h1>
 <p>Görselli kampanyalar oluştur, önizle, test et ve İYS doğrulanmış izinli alıcılara kontrollü gönder.</p></div>
 <a class="admin-primary" href="{{ route('admin.marketing.create') }}">+ Yeni kampanya</a>
</div>
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>Gönderim güvenliği</h2></div>
 <p class="admin-description">
 {{ $settings->ready() ? 'Kurumsal bilgiler ve manuel İYS kayıt beyanı tamamlandı.' : 'Kurumsal bilgiler ve İYS kayıt beyanı henüz tamamlanmadı; toplu gönderim kapalı.' }}
 &nbsp;Yalnızca <strong>{{ $eligibleCount }}</strong> izinli, İYS doğrulanmış adres hedeflenebilir.
 @if(\App\Models\EmailSetting::previewEnvironment())
 <strong>Test mağazası:</strong> bütün e-postalar güvenli test alıcısına yönlendirilir; kampanya başına en fazla 3 test gönderimi.
 @elseif(!config('newtic.marketing_live_enabled'))
 <strong>Canlı kampanya gönderimi sunucu yapılandırmasında kapalı.</strong>
 @endif
 </p>
 <a class="admin-secondary" href="{{ route('admin.marketing.contacts') }}">Alıcı izinleri ve İYS bilgilerini yönet</a>
</section>
<section class="admin-panel" style="padding:22px">
 <div class="admin-panel-title"><h2>Oluşturulan kampanyalar</h2></div>
 <div style="overflow-x:auto">
 <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
  <thead><tr><th style="padding:12px">Kampanya</th><th>Durum</th><th>Hedef</th><th>SMTP kabul</th><th>Hata</th><th>Atlanan</th><th>İşlem</th></tr></thead>
  <tbody>
  @forelse($campaigns as $item)
  <tr style="border-top:1px solid #e6eaf0">
   <td style="padding:12px"><strong>{{ $item->title }}</strong><small style="display:block">{{ $item->created_at?->format('d.m.Y H:i') }}</small></td>
   <td>{{ ['draft'=>'Taslak','queuing'=>'Alıcılar hazırlanıyor','sending'=>'Gönderim sürüyor','paused'=>'Duraklatıldı','completed'=>'Tamamlandı','cancelled'=>'İptal edildi'][$item->status] ?? $item->status }}</td>
   <td>{{ $item->recipients_count }}</td><td>{{ $item->sent_count }}</td><td>{{ $item->failed_count }}</td><td>{{ $item->skipped_count }}</td>
   <td><a href="{{ route('admin.marketing.edit',$item) }}">Aç / Rapor</a></td>
  </tr>
  @empty
  <tr><td colspan="7" style="padding:18px">Henüz kampanya oluşturmadınız.</td></tr>
  @endforelse
  </tbody>
 </table>
 </div>
 {{ $campaigns->links() }}
 <p class="admin-description">“Gönderildi”, SMTP sunucusunun iletiyi kabul ettiği anlamına gelir; gelen kutusuna teslim veya açılma garantisi değildir.</p>
</section>
@endsection
