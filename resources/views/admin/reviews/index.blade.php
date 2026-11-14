@extends('admin.layout')
@section('title', 'Ürün Yorumları | NEWTIC')
@section('topbar', 'Ürün Yorumları')
@section('content')
<div class="admin-page-heading admin-heading-actions">
  <div>
    <p class="admin-eyebrow">MAĞAZA / MÜŞTERİ DENEYİMİ</p>
    <h1>Ürün yorumları</h1>
    <p>Gerçek satın alma değerlendirmelerini okuyun, kontrol edin ve yayınlayın.</p>
  </div>
  <span class="admin-review-pending-count">{{ (int) ($counts['pending'] ?? 0) }} onay bekliyor</span>
</div>
<section class="admin-panel admin-reviews-panel">
  <nav class="admin-catalog-filters" aria-label="Yorum durumu filtreleri">
    @foreach(['pending' => 'Onay bekleyenler', 'approved' => 'Yayında', 'rejected' => 'Reddedilen', 'all' => 'Tümü'] as $value => $label)
      <a href="{{ route('admin.reviews.index', ['status' => $value]) }}"
        @class(['admin-filter-pill', 'is-active' => $status === $value])>
        {{ $label }}
        @if($value !== 'all')<small>{{ (int) ($counts[$value] ?? 0) }}</small>@endif
      </a>
    @endforeach
  </nav>

  <div class="admin-review-queue">
    @forelse($reviews as $review)
      <article class="admin-review-card">
        <div class="admin-review-top">
          <div>
            <a href="{{ $review->product ? route('product.show', $review->product->slug) : '#' }}" target="_blank" rel="noopener noreferrer" class="admin-review-product">
              {{ $review->product?->name ?? 'Silinmiş ürün' }} <x-admin-icon name="external-link" />
            </a>
            <p>{{ $review->user?->name ?? 'Eski üye' }}
              · {{ $review->order?->order_number ?? 'Sipariş bulunamadı' }}
              · {{ $review->created_at->format('d.m.Y H:i') }}
            </p>
          </div>
          <div class="admin-review-tags">
            @if($review->is_test)<span class="admin-test-tag" title="Bu yorum gerçek mağaza ortalamasını etkilemez">TEST YORUMU</span>@endif
            <span @class(['admin-state-pill', 'state-pending_payment' => $review->status === 'pending', 'state-completed' => $review->status === 'approved', 'state-cancelled' => $review->status === 'rejected'])>
              {{ match($review->status) {
                'pending' => 'Onay bekliyor',
                'approved' => $review->is_test
                    ? (\App\Support\ProductReviewVisibility::showsTestReviews() ? 'Test önizlemesinde' : 'Gerçek mağazada gizli')
                    : 'Yayında',
                'rejected' => 'Reddedildi',
                default => 'Bilinmiyor'
              } }}
            </span>
          </div>
        </div>
        <div class="admin-review-rating" aria-label="{{ $review->rating }} üzerinden 5 yıldız">
          @for($star = 1; $star <= 5; $star++)
            <span @class(['is-active' => $star <= $review->rating]) aria-hidden="true">★</span>
          @endfor
          <strong>{{ $review->rating }}/5</strong>
        </div>
        <p class="admin-review-body">{{ $review->body }}</p>
        @if($review->status === 'pending')
          <div class="admin-review-actions">
            <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}">
              @csrf
              <input type="hidden" name="decision" value="approved">
              <button class="admin-primary" type="submit"><x-admin-icon name="check" /> Onayla</button>
            </form>
            <form method="POST" action="{{ route('admin.reviews.moderate', $review) }}">
              @csrf
              <input type="hidden" name="decision" value="rejected">
              <button class="admin-danger" type="submit"><x-admin-icon name="close" /> Reddet</button>
            </form>
            @if($review->is_test)<small>Bu değerlendirme onaylansa bile gerçek mağaza puanına katılmaz.</small>@endif
          </div>
        @endif
      </article>
    @empty
      <p class="admin-empty">Bu filtrede değerlendirme bulunmuyor. Yeni yorumlar burada görünecek.</p>
    @endforelse
  </div>
  {{ $reviews->links() }}
</section>
@endsection
