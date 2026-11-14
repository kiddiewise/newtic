@extends('admin.layout')
@section('title','E-posta ve Bildirimler | NEWTIC')
@section('topbar','E-posta ve Bildirimler')
@section('content')
<div class="admin-page-heading">
  <p class="admin-eyebrow">MAĞAZA AYARLARI / İLETİŞİM</p>
  <h1>E-posta ve bildirim merkezi</h1>
  <p>Sipariş, ödeme, teslimat ve ürün yorumları için otomatik mesajları yönetin. Mağaza bazında bağımsız ve güvenli SMTP bağlantısı.</p>
</div>
<section class="admin-panel" style="margin-bottom:20px;padding:22px">
  <div class="admin-panel-title"><h2>Gönderim durumu</h2></div>
  <p><strong>{{ $settings->active() ? 'Bildirimler etkin' : ($settings->verified_at ? 'SMTP doğrulandı · Bildirimler kapalı' : 'Bağlantı doğrulanmadı · Bildirimler kapalı') }}</strong></p>
  @if(\App\Models\EmailSetting::previewEnvironment())
    <p class="admin-description"><strong>Test güvenliği:</strong> Bu mağaza test ortamında. İşlemsel iletiler müşterilere değil yalnızca aşağıdaki güvenli test adresine yönlendirilir. Gerçek müşteri e-postaları gönderilmez.</p>
  @endif
  <div style="display:flex;flex-wrap:wrap;gap:12px">
    @foreach(['pending'=>'Bekleyen','retry'=>'Yeniden denenecek','sent'=>'Gönderilen','failed'=>'Başarısız','skipped'=>'Atlanan'] as $key=>$label)
      <span style="padding:10px 14px;background:#f5f7fa;border-radius:9px"><strong>{{ (int)($counts[$key]??0) }}</strong> {{ $label }}</span>
    @endforeach
  </div>
</section>
<section class="admin-panel" style="margin-bottom:20px;padding:22px">
  <div class="admin-panel-title"><h2>SMTP ve gönderici bilgileri</h2></div>
  <form method="POST" action="{{ route('admin.email-center.save') }}" class="admin-form" autocomplete="off">
    @csrf @method('PATCH')
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px">
      <label>SMTP sunucusu <input name="smtp_host" required maxlength="255" value="{{ old('smtp_host',$settings->smtp_host) }}" placeholder="smtp.ornekmagaza.com"></label>
      <label>SMTP portu <input name="smtp_port" type="number" min="1" max="65535" required value="{{ old('smtp_port',$settings->smtp_port) }}"></label>
      <label>Güvenli bağlantı
        <select name="smtp_scheme">
          <option value="smtp" @selected(old('smtp_scheme',$settings->smtp_scheme)==='smtp')>STARTTLS / SMTP (genellikle 587)</option>
          <option value="smtps" @selected(old('smtp_scheme',$settings->smtp_scheme)==='smtps')>SSL/TLS / SMTPS (genellikle 465)</option>
        </select>
      </label>
      <label>SMTP kullanıcı adı <input name="smtp_username" required maxlength="255" value="{{ old('smtp_username',$settings->smtp_username) }}" autocomplete="off"></label>
      <label>SMTP parolası <input name="smtp_password" type="password" maxlength="2048" autocomplete="new-password" placeholder="{{ $settings->configured() ? 'Şifreli kayıtlı · Değiştirmek için yazın' : 'SMTP parolası' }}">
        <small>Boş bırakılırsa mevcut parola korunur; değeri panelde tekrar gösterilmez.</small></label>
      <label>Gönderen e-posta <input name="from_address" type="email" required maxlength="255" value="{{ old('from_address',$settings->from_address) }}" placeholder="siparis@ornekmagaza.com"></label>
      <label>Gönderen adı <input name="from_name" required maxlength="140" value="{{ old('from_name',$settings->from_name ?: \App\Support\StoreIdentity::name()) }}"></label>
      <label>Yanıt adresi <input name="reply_to" type="email" maxlength="255" value="{{ old('reply_to',$settings->reply_to) }}" placeholder="destek@ornekmagaza.com"></label>
      <label>Güvenli test alıcısı <input name="test_recipient" type="email" maxlength="255" value="{{ old('test_recipient',$settings->test_recipient) }}" placeholder="test@ornekmagaza.com" @required(\App\Models\EmailSetting::previewEnvironment())>
        <small>Test mağazasında tüm bildirimlerin gideceği tek adres.</small></label>
      <label>Yorum daveti gecikmesi (gün) <input name="review_delay_days" type="number" min="1" max="14" required value="{{ old('review_delay_days',$settings->review_delay_days) }}"></label>
    </div>
    <label style="margin-top:14px">Yönetici alıcı e-postaları (virgül veya yeni satırla ayırın)
      <textarea name="admin_emails" rows="3" maxlength="2000" placeholder="satis@ornekmagaza.com">{{ old('admin_emails',$settings->admin_emails) }}</textarea>
      <small>Yeni sipariş, ödeme bildirimi, kargo istisnası ve yorum onayı e-postaları buraya gider.</small>
    </label>
    <div class="admin-panel-title" style="margin-top:25px"><h2>Hangi olaylarda e-posta gönderilsin?</h2></div>
    <p class="admin-description">Bir olayı kapatırsanız henüz gönderilmemiş bildirimleri de durdurursunuz. Yorum davetleri yalnızca teslim edilmiş, üye hesaplı siparişlerde bir kere hazırlanır. <strong>Yorum davetleri ilk kurulumda kapalıdır; yasal/iletişim politikası kontrolünden sonra ayrıca işaretleyin.</strong></p>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(270px,1fr));gap:8px;margin:14px 0">
      @foreach(\App\Models\EmailSetting::EVENTS as $code=>$label)
        <label style="display:flex;align-items:center;gap:9px;padding:10px 12px;border:1px solid #e1e6ee;border-radius:9px">
          <input type="checkbox" name="events[]" value="{{ $code }}" @checked(in_array($code, old('events',$settings->events ?? \App\Models\EmailSetting::defaultEvents()),true))>
          <span style="font-size:13px">{{ $label }}</span>
        </label>
      @endforeach
    </div>
    <label style="display:flex;gap:12px;align-items:center;margin:20px 0">
      <input type="hidden" name="enabled" value="0">
      <input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings->enabled))>
      <span><strong>Otomatik e-postaları etkinleştir</strong><small> SMTP testini başarıyla tamamlamadan açılmaz.</small></span>
    </label>
    <button type="submit" class="admin-primary">Ayarları güvenle kaydet</button>
  </form>
  <form method="POST" action="{{ route('admin.email-center.test') }}" style="margin-top:14px;display:inline-flex">
    @csrf <button class="admin-secondary" type="submit">SMTP test e-postası gönder</button>
  </form>
  <p class="admin-description" style="margin-top:12px">Teslimat garantisi SMTP kabulünün ötesinde SPF, DKIM, DMARC ve alıcı sunucu kontrollerine bağlıdır. Test etmeden gerçek gönderimleri açmayın. Kampanya e-postaları bu modülde bulunmaz.</p>
</section>
<section class="admin-panel" style="padding:22px">
  <div class="admin-panel-title"><h2>E-posta işlem geçmişi</h2></div>
  <form method="POST" action="{{ route('admin.email-center.process') }}" style="margin:15px 0">
    @csrf <button class="admin-secondary" type="submit">Bekleyenleri şimdi işle</button>
  </form>
  <div style="overflow-x:auto">
    <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
      <thead><tr><th style="padding:12px">Olay</th><th>Alıcı</th><th>Durum</th><th>Deneme</th><th>Oluşturulma</th><th>İşlem</th></tr></thead>
      <tbody>
      @forelse($deliveries as $row)
        <tr style="border-top:1px solid #e6eaf0">
          <td style="padding:12px">{{ \App\Models\EmailSetting::EVENTS[$row->event] ?? $row->event }}</td>
          <td>{{ \App\Models\EmailSetting::previewEnvironment() ? 'Test alıcısı' : $row->recipient_email }}</td>
          <td>{{ ['pending'=>'Bekliyor','retry'=>'Tekrar denenecek','processing'=>'İşleniyor','sent'=>'Gönderildi','failed'=>'Başarısız','skipped'=>'Gönderilmedi'][$row->status] ?? $row->status }}
            @if($row->last_error)<small>{{ $row->last_error }}</small>@endif
          </td>
          <td>{{ $row->attempts }}</td><td>{{ $row->created_at?->format('d.m.Y H:i') }}</td>
          <td>@if($row->status==='failed')
            <form method="POST" action="{{ route('admin.email-center.retry',$row) }}">@csrf<button class="admin-secondary" type="submit">Yeniden dene</button></form>
          @endif</td>
        </tr>
      @empty
        <tr><td colspan="6" style="padding:16px">Henüz bir bildirim kaydı yok. SMTP testleri gerçek sipariş bildirimi oluşturmaz.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
  {{ $deliveries->links() }}
</section>
@endsection
