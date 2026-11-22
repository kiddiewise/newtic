@extends('setup.layout')
@section('title','Sistem ön kontrolü')
@section('content')
  <div class="eyebrow">2 / 3 · SUNUCU KONTROLÜ</div>
  <h1>Sunucunuz hazır mı?</h1>
  <p>Bu ekran yeni mağazanızın bağımsız veritabanını, PHP ortamını, güvenli başlangıç ayarlarını ve ön yüz dosyalarını kontrol eder. Hassas bilgiler görüntülenmez.</p>
  <div class="notice"><strong>Güvenli başlangıç:</strong> Bu aşama ödeme, kargo veya toplu e-posta servisini açmaz. Her entegrasyon ilk kurulumdan sonra ayrıca onaylanır.</div>
  <div class="steps"><span class="active"></span><span class="active"></span><span></span></div>
  <ul class="checks">
    @foreach($checks as $name => $ok)
      <li><span>{{ $name }}</span>
        <span class="{{ $ok ? 'success' : 'failed' }}">{{ $ok ? '✓ Uygun' : '× Düzeltilmeli' }}</span>
      </li>
    @endforeach
  </ul>
  @unless($ready)
    <div class="errors" role="alert">Bazı kontroller başarısız. Lütfen kendi hosting panelinizden veya SSH terminalinden gerekli düzeltmeleri yapıp sayfayı yenileyin. Gizli verileri destek mesajlarında paylaşmayın.</div>
  @else
    <div class="notice"><strong>Kurulum kontrolleri başarılı.</strong> İlk mağaza ve yönetici bilgilerini oluşturabilirsiniz. Tamamlandığında kurulum yolu kalıcı olarak kapanır.</div>
  @endunless
  <div class="actions">
    <a href="{{ route('install.checks') }}" style="color:#092037">Kontrolleri yenile</a>
    <a href="{{ $ready ? route('install.details') : route('install.checks') }}" class="button {{ $ready ? '' : 'disabled' }}"
      @unless($ready) aria-disabled="true" @endunless>Mağaza bilgilerine devam et</a>
  </div>
@endsection
