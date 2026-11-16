@extends('layouts.store')
@section('content')
<main class="legal-content-shell container" id="content">
  <nav class="legal-breadcrumb" aria-label="Sayfa konumu">
    <a href="{{ route('home') }}">Ana sayfa</a><span aria-hidden="true">/</span>
    <span>{{ $page->title }}</span>
  </nav>
  <article class="legal-paper">
    <div class="legal-paper-label">KURUMSAL BİLGİLENDİRME</div>
    <h1>{{ $page->title }}</h1>
    <div class="legal-paper-meta">{{ \App\Support\StoreIdentity::name() }} · Güncelleme: {{ $page->updated_at?->format('d.m.Y') }}</div>
    <div class="legal-paper-body">{{ $page->body }}</div>
  </article>
  @if($page->slug === 'iletisim' && $merchant->ready())
    <section class="legal-paper legal-contact-details">
      <h2>Satıcı iletişim bilgileri</h2>
      <dl>
        <dt>Ticari unvan</dt><dd>{{ $merchant->legal_name }}</dd>
        <dt>Adres</dt><dd>{{ $merchant->registered_address }}</dd>
        <dt>E-posta</dt><dd><a href="mailto:{{ $merchant->contact_email }}">{{ $merchant->contact_email }}</a></dd>
        <dt>Telefon</dt><dd>{{ $merchant->contact_phone }}</dd>
        @if($merchant->kep_address)<dt>KEP</dt><dd>{{ $merchant->kep_address }}</dd>@endif
        @if($merchant->mersis_number)<dt>MERSİS</dt><dd>{{ $merchant->mersis_number }}</dd>@endif
        @if($merchant->support_hours)<dt>Çalışma saatleri</dt><dd>{{ $merchant->support_hours }}</dd>@endif
      </dl>
    </section>
  @endif
</main>
@endsection
