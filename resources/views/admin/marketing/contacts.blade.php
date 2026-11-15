@extends('admin.layout')
@section('title','Kampanya Alıcıları ve İYS | NEWTIC')
@section('topbar','Kampanya Alıcıları')
@section('content')
<div class="admin-page-heading"><p class="admin-eyebrow">KAMPANYALAR / İZİN YÖNETİMİ</p>
<h1>Kampanya alıcıları ve izinler</h1><p>Müşteri listesi otomatik pazarlama listesi değildir. Onay belgesi ve İYS kaydı olmayan adreslere kampanya gönderilmez.</p></div>
<p><a href="{{ route('admin.marketing.index') }}">← Kampanyalara dön</a></p>
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>Şirket kimliği ve İYS kayıt beyanı</h2></div>
 <p class="admin-description">Her şirket kendi unvanı, iletişim bilgisi ve İYS kaydıyla çalışır. Bu panel <strong>İYS API'sine bağlı değildir</strong>; girdiğiniz kayıtları işletme olarak doğruladığınızı beyan edersiniz. Canlı gönderim ayrıca sunucuda açılmalıdır.</p>
 <form method="POST" action="{{ route('admin.marketing.settings') }}" class="admin-form">
  @csrf @method('PATCH')
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px">
   <label>Ticari unvan / şirket adı *<input name="business_name" required maxlength="180" value="{{ old('business_name',$settings->business_name) }}"></label>
   <label>MERSİS / mevzuata uygun işletme kimliği *<input name="business_identifier" required maxlength="100" value="{{ old('business_identifier',$settings->business_identifier) }}"></label>
   <label>İletişim e-postası *<input name="contact_email" type="email" required value="{{ old('contact_email',$settings->contact_email) }}"></label>
   <label>İYS hizmet sağlayıcı kayıt numarası *<input name="iys_service_provider_id" required maxlength="120" value="{{ old('iys_service_provider_id',$settings->iys_service_provider_id) }}"></label>
  </div>
  <label>Şirket adresi *<textarea name="business_address" required rows="3" minlength="12" maxlength="2000">{{ old('business_address',$settings->business_address) }}</textarea></label>
  <label style="display:flex;gap:9px;align-items:center"><input type="checkbox" name="iys_checked" value="1" required> <span>Bu mağazanın İYS kaydı ve firma bilgilerini güncel olarak doğruladım.</span></label>
  <input type="hidden" name="enabled" value="0">
  <label style="display:flex;gap:9px;align-items:center"><input type="checkbox" name="enabled" value="1" @checked(old('enabled',$settings->enabled))> <span>Kampanya gönderimine işletme olarak izin ver</span></label>
  <button class="admin-primary">Kurumsal bilgileri kaydet</button>
 </form>
</section>
<section class="admin-panel" style="padding:22px;margin-bottom:18px">
 <div class="admin-panel-title"><h2>İzinli adres ekle / doğrula</h2></div>
 <p class="admin-description">Mevcut bir müşterinin e-postasını da buradan İYS kaydıyla eşleştirebilirsiniz. Yeni alıcının adresini girmek veya müşteri olması tek başına izin oluşturmaz.</p>
 <form class="admin-form" method="POST" action="{{ route('admin.marketing.contact.store') }}">
  @csrf
  <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px">
   <label>Alıcı e-posta adresi *<input type="email" name="email" required maxlength="255" value="{{ old('email') }}"></label>
   <label>Adı / Soyadı <input name="name" maxlength="160" value="{{ old('name') }}"></label>
   <label>Onay kaynağı *
    <select name="consent_source" required>
      <option value="iys">İYS kaydı</option><option value="document">İmzalı onay belgesi</option>
      <option value="external_record">Başka sistemden aktarılmış açık onay</option>
    </select></label>
   <label>Onay tarihi *<input type="datetime-local" name="consent_at" required value="{{ old('consent_at') }}"></label>
   <label>İYS kayıt referansı *<input name="iys_reference" required minlength="3" maxlength="190" value="{{ old('iys_reference') }}"></label>
  </div>
  <label>Onay kanıtı / dayanağı *<textarea name="consent_proof" required minlength="12" maxlength="2000" rows="3" placeholder="Belge numarası, izin tarihi, onay yöntemi, kayıt kaynağı…">{{ old('consent_proof') }}</textarea></label>
  <label style="display:flex;gap:9px;align-items:center"><input type="checkbox" name="iys_verified" value="1" required> <span>Alıcının güncel İYS onayını kontrol ettim. Ret veya iptal kaydı bulunmuyor.</span></label>
  <button class="admin-primary">İzinli alıcıyı kaydet</button>
 </form>
</section>
<section class="admin-panel" style="padding:22px">
 <div class="admin-panel-title"><h2>İzin kayıtları · {{ $eligibleCount }} gönderime uygun</h2></div>
 <div style="overflow-x:auto">
 <table style="width:100%;border-collapse:collapse;text-align:left;font-size:13px">
 <thead><tr><th style="padding:12px">Alıcı</th><th>İzin durumu</th><th>İYS</th><th>İşlem</th></tr></thead>
 <tbody>
 @forelse($contacts as $contact)
 <tr style="border-top:1px solid #e6eaf0">
 <td style="padding:12px">{{ $contact->email }}<small style="display:block">{{ $contact->name }}</small></td>
 <td>{{ $contact->unsubscribed_at ? 'Abonelikten çıktı' : ($contact->eligibleToSend() ? 'Onaylı' : ($contact->status==='pending' ? 'İYS doğrulaması bekliyor' : 'Gönderim kapalı')) }}</td>
 <td>{{ $contact->iys_confirmed_at?->format('d.m.Y') ?? '—' }}</td>
 <td>@if(!$contact->unsubscribed_at && $contact->status!=='suppressed')
 <form method="POST" action="{{ route('admin.marketing.contact.suppress',$contact) }}">@csrf
 <button class="admin-secondary" type="submit">Gönderimi durdur</button></form>@endif</td>
 </tr>
 @empty <tr><td colspan="4" style="padding:15px">Henüz izin kaydı yok.</td></tr>@endforelse
 </tbody></table></div>
 {{ $contacts->links() }}
 <p class="admin-description">Müşteri kendi hesabından veya e-postadaki abonelikten çık bağlantısından iznini geri çektiğinde kampanya iletileri hemen durdurulur.</p>
</section>
@endsection
