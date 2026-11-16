@extends('layouts.store')
@section('content')
<main class="legal-content-shell container" id="content">
  <div class="legal-paper legal-google-review">
    <div class="legal-paper-label">GOOGLE İLE İLK ÜYELİK</div>
    <h1>Üyelik koşullarını inceleyin.</h1>
    <p>Google hesabınız doğrulandı. NEWTIC mağazasında ilk müşteri hesabınız oluşturulmadan önce üyelik koşullarını onaylamanız gerekiyor. Bu işlem kampanya e-postalarına izin vermez.</p>
    @if($errors->any())
      <div class="checkout-error" role="alert">{{ $errors->first() }}</div>
    @endif
    <section class="legal-google-document">
      <h2>{{ $page->title }}</h2>
      <p class="legal-paper-meta">Sürüm {{ $page->version }} · {{ $page->updated_at?->format('d.m.Y') }}</p>
      <div class="legal-paper-body">{{ $page->body }}</div>
    </section>
    <form method="post" action="{{ route('account.google.review.submit') }}" class="checkout-form">
      @csrf
      <input type="hidden" name="membership_revision" value="{{ $page->revisionHash() }}">
      <label class="checkout-check legal-checkout-ack">
        <input type="checkbox" name="membership_accepted" value="1" required @checked(old('membership_accepted'))>
        <span>Yukarıdaki üyelik koşullarını okudum ve kabul ediyorum.</span>
      </label>
      @if(\App\Models\MerchantLegalPage::published('kvkk-aydinlatma'))
        <p class="legal-mini-note">Kişisel verilerin işlenmesi hakkında
          <a target="_blank" rel="noopener noreferrer" href="{{ route('legal.show', 'kvkk-aydinlatma') }}">KVKK Aydınlatma Metni</a>'ni inceleyebilirsiniz.</p>
      @endif
      <button class="checkout-action auth-submit" type="submit">Google üyeliğimi tamamla</button>
    </form>
    <p class="legal-mini-note">İşlemi tamamlamazsanız hesap oluşturulmaz. Bağlantı 10 dakika geçerlidir.</p>
  </div>
</main>
@endsection
