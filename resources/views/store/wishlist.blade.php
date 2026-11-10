@extends('layouts.store')
@section('title', 'Favorilerim | Polaris Kurşun')
@section('content')
<main id="content" class="container wishlist-page">
  <div class="wishlist-heading">
    <a href="{{ route('cart.index') }}" aria-label="Sepete geri dön">←</a>
    <h1>Favorilerim</h1>
    <a href="{{ route('search') }}" class="wishlist-to-search">Ürünleri keşfet ↗</a>
  </div>
  @if(session('success'))<p class="cart-flash is-success" role="status">{{ session('success') }}</p>@endif
  @if(session('error'))<p class="cart-flash is-error" role="alert">{{ session('error') }}</p>@endif
  <p class="wishlist-info">Favori ürünlerin hesabına kaydedilir. Giriş yaptığında favorilerine yeniden ulaşabilirsin.</p>
  @if($products->isEmpty())
    <div class="cart-empty"><h2>Henüz favori ürünün yok</h2><p>Sepetten kalp simgesine dokunarak ürünleri favorilere ekleyebilirsin.</p>
      <a class="cart-primary" href="{{ route('search') }}">Ürünleri keşfet</a>
    </div>
  @else
    <div class="wishlist-grid">
      @foreach($products as $product)
        <article class="wishlist-card">
          <a href="{{ route('product.show', $product->slug) }}">
            <img src="{{ asset($product->image_path ?: 'assets/products/demo-sinker.svg') }}" alt="{{ $product->name }}" width="240" height="240" loading="lazy">
            <h2>{{ $product->name }}</h2>
            <strong>{{ number_format((float) $product->price, 2, ',', '.') }} ₺</strong>
          </a>
          <form method="POST" action="{{ route('wishlist.toggle', $product) }}">
            @csrf <button type="submit" class="wishlist-remove">♥ Favorilerden çıkar</button>
          </form>
        </article>
      @endforeach
    </div>
  @endif
</main>
@endsection
