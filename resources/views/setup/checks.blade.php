@extends('setup.layout')
@section('title','Sistem ön kontrolü')
@section('content')
  <div class="eyebrow">2 / 3 · SUNUCU KONTROLÜ</div>
  <h1>Sunucunuz hazır mı?</h1>
  <p>Kurulum mevcut veritabanına veya başka müşterinin ürünlerine müdahale etmeden önce gereksinimleri denetler.</p>
  <div class="steps"><span class="active"></span><span class="active"></span><span></span></div>
  <ul class="checks">
    @foreach($checks as $name => $ok)
      <li><span>{{ $name }}</span>
        <span class="{{ $ok ? 'success' : 'failed' }}">{{ $ok ? '✓ Uygun' : '× Düzeltilmeli' }}</span>
      </li>
    @endforeach
  </ul>
  @unless($ready)
    <div class="errors">Bazı kontroller başarısız. Sunucu ayarlarını SSH veya hPanel üzerinden düzeltin, sonra bu sayfayı yenileyin. Gizli veriler burada gösterilmez.</div>
  @else
    <div class="notice">Sistem hazır. İlk mağaza ve yönetici bilgilerini oluşturabilirsiniz. Ödeme ve kargo testleri bu aşamada etkinleştirilmeyecek.</div>
  @endunless
  <div class="actions">
    <a href="{{ route('install.checks') }}" style="color:#092037">Kontrolleri yenile</a>
    <a href="{{ $ready ? route('install.details') : route('install.checks') }}" class="button {{ $ready ? '' : 'disabled' }}"
      @unless($ready) aria-disabled="true" @endunless>Mağaza bilgilerine devam et</a>
  </div>
@endsection
