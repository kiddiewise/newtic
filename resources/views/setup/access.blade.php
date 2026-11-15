@extends('setup.layout')
@section('title','Kuruluma hoş geldiniz')
@section('content')
  <div class="eyebrow">1 / 3 · KURULUM YETKİSİ</div>
  <h1>Mağazanız için ilk adım.</h1>
  <p>Yalnızca yeni ve boş bir NEWTIC mağazasında çalışır. Özel SSH terminalinde oluşturduğunuz kısa ömürlü anahtarı girin.</p>
  <div class="steps"><span class="active"></span><span></span><span></span></div>
  <div class="notice">Kurulum anahtarınızı SSH terminalinde <code>php artisan newtic:setup-token</code> komutuyla oluşturabilirsiniz. Anahtarı e-posta veya mesajla paylaşmayın.</div>
  <form method="post" action="{{ route('install.unlock') }}" autocomplete="off">
    @csrf
    <label for="setup_token">Tek kullanımlık kurulum anahtarı</label>
    <input id="setup_token" name="setup_token" type="password" minlength="64" maxlength="64" pattern="[0-9a-f]{64}"
      placeholder="64 karakterli güvenli anahtar" autocomplete="off" spellcheck="false" required autofocus>
    <div class="actions"><span style="color:#8194a1;font-size:12px">2 saat geçerli · 10 başarısız deneme/dakika sınırı</span>
      <button type="submit">Sistem kontrolüne geç</button></div>
  </form>
@endsection
