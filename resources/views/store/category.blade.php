@extends('layouts.store')
@section('title', $category->name.' | Polaris Kurşun')
@section('content')
<main class="container inner-page" id="content">
  <nav class="breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><span>{{ $category->name }}</span></nav>
  <div class="page-intro"><p class="eyebrow">ÜRÜN KATEGORİSİ</p><h1>{{ $category->name }}</h1><p>Ürünleri inceleyin, gramajları karşılaştırın.</p></div>
  <div class="product-grid">@forelse($products as $product) @include('store.partials.product-card', ['product' => $product]) @empty <p class="nothing-found">Bu kategoride henüz ürün bulunmuyor.</p> @endforelse</div>
  <div class="pagination-wrap">{{ $products->links() }}</div>
</main>
@endsection
