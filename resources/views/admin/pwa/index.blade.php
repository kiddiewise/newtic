@extends('admin.layout')
@section('title','Mobil Uygulama ve Bildirimler | NEWTIC')
@section('content')
<div class="newtic-pwa" data-admin-pwa data-status="{{ route('admin.pwa.status',[],false) }}" data-subscribe="{{ route('admin.pwa.subscribe',[],false) }}" data-test="{{ route('admin.pwa.test',[],false) }}">
  <div class="admin-page-heading"><div><p class="admin-eyebrow">YÖNETİM / MOBİL</p><h1>Mobil Uygulama</h1><p>NEWTIC'i telefonuna ekle, siparişlerini kaçırma.</p></div></div>
  <section class="admin-panel newtic-pwa-card">
    <div class="newtic-pwa-title"><img src="{{ asset('assets/pwa/newtic-admin-192.png') }}" alt="" width="54" height="54"><div><h2>NEWTIC Yönetim</h2><p>Ana ekrandan doğrudan erişim</p></div></div>
    <button class="admin-secondary" type="button" data-pwa-install hidden>Uygulamayı yükle</button>
    <p data-pwa-install-help>Android: Chrome menüsünden Uygulamayı yükle veya Ana ekrana ekle. iPhone: Safari paylaşım menüsünden Ana Ekrana Ekle.</p>
  </section>
  <section class="admin-panel newtic-pwa-card">
    <h2>Sipariş bildirimleri</h2><p>Bu cihazda yeni siparişler için sistem bildirimi al. Telefonun sessiz mod ve bildirim tercihleri geçerlidir.</p>
    <p data-pwa-state role="status" aria-live="polite">Cihaz kontrol ediliyor…</p>
    <div class="newtic-pwa-actions">
      <button class="admin-primary" type="button" data-pwa-enable disabled>Bildirimleri aç</button>
      <button class="admin-secondary" type="button" data-pwa-disable disabled>Bildirimleri kapat</button>
      <button class="admin-secondary" type="button" data-pwa-test disabled>Test bildirimi gönder</button>
    </div>
    <small>İzin cihaz bazındadır. Abonelik 30 gün geçerlidir; bu sayfadan yeniden etkinleştirebilirsin. Bildirimler, sunucu cron görevi çalıştığında yaklaşık bir dakika içinde gönderime alınır. Bildirimde müşteri bilgisi bulunmaz.</small>
  </section>
  <section class="admin-panel newtic-pwa-card">
    <h2>NEWTIC sipariş sesi</h2>
    <p>Yönetim paneli açıkken yeni siparişler için kısa kasa sesi çalar. Üst menüdeki ses düğmesinden etkinleştirebilirsin. Telefon kilitliyken sistem bildirim sesi geçerlidir.</p>
    <div class="newtic-pwa-actions"><button type="button" class="admin-secondary" data-admin-sound-preview>Kasa sesini dinle</button>
      <a class="admin-secondary" href="{{ asset('assets/pwa/newtic-kasa-sesi.wav') }}" download="newtic-kasa-sesi.wav">Sesi indir</a></div>
    <small data-admin-sound-preview-status role="status" aria-live="polite"></small>
    <small>Android cihazında özel bildirim sesi seçimi destekleniyorsa indirilen dosyayı telefon ayarlarından seçebilirsin.</small>
  </section>
  <section class="admin-panel newtic-pwa-card"><h2>Güvenli kullanım</h2><p>Çevrimdışı sipariş verisi tutulmaz. Yönetim panelinden çıkış yaptığında bu oturumun sunucu bildirimi kapatılır. Bildirim açıldığında ödeme ve kargo işlemleri değişmez.</p></section>
</div>
@endsection
