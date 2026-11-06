@extends('layouts.store')
@section('title', 'Ürün arama | Polaris Kurşun')
@section('content')
<main class="container inner-page" id="content">
  <nav class="breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><span>Ürünler</span></nav>
  <div class="page-intro"><p class="eyebrow">ÜRÜN KATALOĞU</p><h1>{{ $term !== '' ? 'Arama: '.$term : 'Tüm ürünler' }}</h1><p>Aradığınız ekipmanlara kolayca ulaşın.</p></div>
  <form class="search-inline" action="{{ route('search') }}" method="get"><input name="q" value="{{ $term }}" maxlength="100" placeholder="Ürün adı yazın..." aria-label="Ürün ara"><button type="submit">Ara</button></form>
  <div class="product-grid">@forelse($products as $product) @include('store.partials.product-card', ['product' => $product]) @empty <p class="nothing-found">Aradığınız kriterde ürün bulunamadı.</p> @endforelse</div>
  <div class="pagination-wrap">{{ $products->links() }}</div>
</main>
@endsection
