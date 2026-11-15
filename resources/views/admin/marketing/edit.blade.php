@extends('admin.layout')
@section('title',($campaign->exists ? 'Kampanyayı Yönet':'Yeni Kampanya').' | NEWTIC')
@section('topbar','Kampanya Oluştur / Yönet')
@section('content')
<div class="admin-page-heading">
 <p class="admin-eyebrow">E-POSTA / KAMPANYA EDİTÖRÜ</p>
 <h1>{{ $campaign->exists ? $campaign->title : 'Yeni kampanya oluştur' }}</h1>
 <p>Mobil uyumlu beyaz e-posta şablonu, 600 piksel banner, kurumsal renkler ve tek eylem butonu kullanılır.</p>
</div>
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>İçerik ve tasarım</h2></div>
 @if($campaign->status === 'draft')
 <form method="POST" enctype="multipart/form-data" action="{{ $campaign->exists ? route('admin.marketing.update',$campaign) : route('admin.marketing.store') }}" class="admin-form">
  @csrf @if($campaign->exists) @method('PATCH') @endif
  <label>Kampanya başlığı / e-posta konusu *
   <input name="title" required minlength="5" maxlength="180" value="{{ old('title',$campaign->title) }}" placeholder="Örneğin: Kasım fırsatları başladı"></label>
  <label>İçerik *
   <textarea name="body" required minlength="15" maxlength="15000" rows="7" placeholder="Kampanya metniniz; satır sonları korunur, tehlikeli HTML çalıştırılmaz.">{{ old('body',$campaign->body) }}</textarea>
  </label>
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px">
   <label>Buton üzerindeki yazı
    <input name="button_text" maxlength="70" value="{{ old('button_text',$campaign->button_text) }}" placeholder="Fırsatları keşfet"></label>
   <label>Buton bağlantısı (HTTPS)
    <input name="button_url" type="url" maxlength="2000" value="{{ old('button_url',$campaign->button_url) }}" placeholder="https://ornekmagaza.com/kampanya"></label>
   <label>Banner / görsel (JPG, PNG, WebP)
    <input type="file" name="banner" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
    <small>Önerilen 1200 × 600 px, ideal 200–500 KB, üst sınır 4 MB.</small></label>
  </div>
  @if($campaign->banner_path)
   <img src="{{ asset($campaign->banner_path) }}" alt="Mevcut kampanya bannerı" style="display:block;width:100%;max-width:490px;height:auto;border-radius:12px;margin:12px 0">
   <label><input type="checkbox" name="remove_banner" value="1"> Mevcut bannerı kaldır</label>
  @endif
  <button type="submit" class="admin-primary">{{ $campaign->exists ? 'Taslağı kaydet' : 'Kampanyayı oluştur' }}</button>
 </form>
 @else
  <p>Bu kampanya başlatıldığı için içeriği değiştirilemez; aynı içeriğin tekrar gönderilmesini önlemek amacıyla kilitlidir.</p>
 @endif
</section>
@if($campaign->exists)
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>Önizleme ve test</h2></div>
 <p class="admin-description">Gerçek kampanya başlamadan önce gelen kutunda tasarımı incele. Test e-postasında gerçek alıcıya gönderim veya abonelikten çıkış işlemi yapılmaz.</p>
 <div style="display:flex;flex-wrap:wrap;gap:10px">
 <a class="admin-secondary" href="{{ route('admin.marketing.preview',$campaign) }}" target="_blank" rel="noopener">E-postayı önizle</a>
 <form method="POST" action="{{ route('admin.marketing.test',$campaign) }}">@csrf
 <button type="submit" class="admin-secondary">Kendime test gönder</button></form>
 </div>
</section>
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>Gönderim yönetimi</h2></div>
 <p>Durum: <strong>{{ ['draft'=>'Taslak','queuing'=>'Alıcılar hazırlanıyor','sending'=>'Gönderim sürüyor','paused'=>'Duraklatıldı','completed'=>'Tamamlandı','cancelled'=>'İptal edildi'][$campaign->status]??$campaign->status }}</strong></p>
 @if($campaign->status==='draft')
 <p class="admin-description">Yalnızca daha önceden açık izin vermiş ve İYS kaydı manuel olarak kontrol edilmiş kişilere gönderilir. Müşteri veya manuel eklenen adres olması tek başına yeterli değildir.</p>
 <form method="POST" action="{{ route('admin.marketing.launch',$campaign) }}">
  @csrf
  <label style="display:flex;gap:9px;align-items:center;margin:14px 0"><input type="checkbox" name="confirm_compliance" value="1" required> <span>İYS kayıtlarını ve pazarlama ileti izinlerini doğruladım.</span></label>
  <label style="display:flex;gap:9px;align-items:center;margin:14px 0"><input type="checkbox" name="confirm_send" value="1" required> <span>Bu kampanyanın izinli alıcılara gönderime alınmasını onaylıyorum.</span></label>
  <button class="admin-primary" type="submit">Kampanyayı bildir / Gönderime al</button>
 </form>
 @elseif(in_array($campaign->status,['queuing','sending']))
 <form method="POST" action="{{ route('admin.marketing.pause',$campaign) }}" style="display:inline-block">@csrf<button class="admin-secondary">Gönderimi duraklat</button></form>
 <form method="POST" action="{{ route('admin.marketing.cancel',$campaign) }}" style="display:inline-block">@csrf<button class="admin-secondary">Kampanyayı tamamen iptal et</button></form>
 @elseif($campaign->status==='paused')
 <form method="POST" action="{{ route('admin.marketing.resume',$campaign) }}" style="display:inline-block">@csrf<button class="admin-primary">Gönderime devam et</button></form>
 <form method="POST" action="{{ route('admin.marketing.cancel',$campaign) }}" style="display:inline-block">@csrf<button class="admin-secondary">Kampanyayı iptal et</button></form>
 @endif
</section>
<section class="admin-panel" style="padding:22px">
 <div class="admin-panel-title"><h2>Gönderim raporu</h2></div>
 <div style="display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px">
 @foreach(['pending'=>'Bekliyor','processing'=>'İşleniyor','retry'=>'Tekrar','sent'=>'SMTP kabul','failed'=>'Başarısız','skipped'=>'Atlandı'] as $key=>$label)
  <span style="background:#f5f7fa;border-radius:8px;padding:10px 12px"><strong>{{ (int)($report[$key]??0) }}</strong> {{ $label }}</span>
 @endforeach
 </div>
 <div style="overflow-x:auto">
  <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
   <thead><tr><th style="padding:10px">Alıcı</th><th>Durum</th><th>Deneme</th><th>Hata sınıfı</th></tr></thead>
   <tbody>@forelse($recipients as $r)
    <tr style="border-top:1px solid #e6eaf0">
     <td style="padding:10px">{{ \App\Models\EmailSetting::previewEnvironment() ? 'Test alıcısı' : $r->contact?->email }}</td>
     <td>{{ $r->status }}</td><td>{{ $r->attempts }}</td><td>{{ $r->last_error }}</td>
    </tr>
   @empty <tr><td colspan="4" style="padding:12px">Henüz gönderim planlanmadı.</td></tr>@endforelse</tbody>
  </table>
 </div>
 @if(method_exists($recipients,'links')) {{ $recipients->links() }} @endif
 <p class="admin-description">Gmail üzerinden gönderim limiti aşılmasını önlemek için dakikada en fazla 10, varsayılan olarak günde 100 kampanya iletisi gönderilir. Bu panel gerçek açılma/tıklama analitiği veya bounce webhook'u sunmaz.</p>
</section>
@endif
@endsection
