@extends('layouts.store')
@section('title', 'Ürün arama | Polaris Kurşun')
@section('content')
<main class="container inner-page" id="content">
  <nav class="breadcrumbs" aria-label="İçerik yolu"><a href="{{ route('home') }}">Ana sayfa</a><span>/</span><span>Ürünler</span></nav>
  <div class="page-intro"><h1 data-search-heading>{{ $term !== '' ? 'Arama: '.$term : 'Tüm ürünler' }}</h1><p>Aradığınız ekipmanlara kolayca ulaşın.</p></div>
  <form class="search-inline" action="{{ route('search') }}" method="get" data-live-catalog-form><input name="q" type="search" value="{{ $term }}" maxlength="100" placeholder="Ürün adı yazın..." aria-label="Ürün ara" autocomplete="off"><button type="submit" aria-label="Ürün ara" title="Ara"><x-store-icon name="search" /></button></form>
  <p class="search-live-status" data-search-status role="status" aria-live="polite"></p>
  <div data-live-search-results aria-busy="false">@include('store.partials.search-results', ['products' => $products])</div>
</main>
@endsection
