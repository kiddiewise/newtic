<section class="pdp-reviews" id="urun-yorumlari" aria-labelledby="product-reviews-title">
  <div class="pdp-reviews-topline">
    <div>
      <p class="review-eyebrow">GERÇEK MÜŞTERİ DENEYİMLERİ</p>
      <h2 id="product-reviews-title">Ürün yorumları</h2>
      <p>Bu gramaja özel, satın almış müşterilerin değerlendirmeleri.</p>
      @if($testReviewCount > 0)
        <p class="review-preview-note"><span>TEST ÖNİZLEMESİ</span> Bu test mağazasında {{ $testReviewCount }} onaylı test yorumu örnek olarak gösterilir. Gerçek mağaza puanlarına dahil değildir.</p>
      @endif
    </div>
    <span class="review-count-pill">{{ $reviewCount }} yorum</span>
  </div>

  <div class="pdp-reviews-overview">
    <div class="review-score">
      <strong>{{ $reviewCount ? number_format($reviewAverage, 1, ',', '.') : '—' }}</strong>
      <span class="review-stars" role="img" aria-label="{{ $reviewCount ? number_format($reviewAverage, 1, ',', '.') . ' üzerinden 5 yıldız' : 'Henüz değerlendirme yok' }}">
        @for($star = 1; $star <= 5; $star++)
          <span @class(['is-lit' => $reviewCount && $reviewAverage >= $star - .5]) aria-hidden="true">★</span>
        @endfor
      </span>
      <small>{{ $reviewCount ? ($testReviewCount > 0 ? 'Test yorumları dahil önizleme puanı' : '5 üzerinden ortalama puan') : 'İlk yorumu siz yapabilirsiniz' }}</small>
    </div>
    <div class="review-distribution" aria-label="Yıldız dağılımı">
      @for($star = 5; $star >= 1; $star--)
        @php($amount = (int) ($ratingCounts[$star] ?? 0))
        <div class="review-distribution-row">
          <span>{{ $star }} <span aria-hidden="true">★</span></span>
          <div class="review-meter" role="meter" aria-label="{{ $star }} yıldız" aria-valuemin="0" aria-valuemax="{{ max(1, $reviewCount) }}" aria-valuenow="{{ $amount }}">
            <span style="width:{{ $reviewCount ? round($amount * 100 / $reviewCount) : 0 }}%"></span>
          </div>
          <small>{{ $amount }}</small>
        </div>
      @endfor
    </div>
  </div>

  <div class="review-write-card" id="yorum-yaz">
    <div class="review-write-heading">
      <span class="review-write-icon"><x-store-icon name="sparkles" /></span>
      <div><h3>Ürünü değerlendirin</h3><p>Deneyiminiz diğer balıkçılara yol göstersin.</p></div>
    </div>
    @if(session('review_success'))
      <div class="review-feedback is-success" role="status"><x-store-icon name="check" /> {{ session('review_success') }}</div>
    @endif
    @if($errors->has('review') || $errors->has('rating') || $errors->has('body'))
      <div class="review-feedback is-error" role="alert">{{ $errors->first('review') ?: ($errors->first('rating') ?: $errors->first('body')) }}</div>
    @endif

    @guest
      <p class="review-help-text">Yorum ve yıldız verebilmek için hesabınıza giriş yapın. Yalnızca tamamlanmış siparişlerde satın aldığınız ürünlere yorum yapabilirsiniz.</p>
      <a class="review-primary-action" href="{{ route('account.login', ['next' => 'review', 'review_product' => $product->slug]) }}">
        <x-store-icon name="user-round" /> Giriş yap ve değerlendir
      </a>
    @else
      @if($myReview)
        <div class="review-own-status">
          <x-store-icon name="check" />
          <span>
            <strong>{{ $myReview->status === 'rejected' ? 'Yorumunuz yayınlanmadı' : ($myReview->status === 'approved' ? ($myReview->is_test ? ($isReviewPreview ? 'Test değerlendirmeniz önizlemede yayınlandı' : 'Test değerlendirmeniz gerçek mağazada gizli') : 'Yorumunuz yayınlandı') : 'Yorumunuz inceleme aşamasında') }}</strong>
            <small>{{ $myReview->is_test ? 'Test siparişi: gerçek mağazanın puanlarına dahil edilmez.' : 'Değişiklik yaptığınızda yorum yeniden onaya gönderilir.' }}</small>
          </span>
        </div>
      @endif
      @if($canReview)
        <form action="{{ route('product.reviews.store', $product) }}" method="POST" class="review-form" data-review-form>
          @csrf
          <fieldset class="review-rating-set">
            <legend>Ürüne kaç yıldız verirsiniz? <span aria-hidden="true">*</span></legend>
            <div class="review-star-picker" data-review-stars>
              @for($star = 5; $star >= 1; $star--)
                <input type="radio" id="product-review-star-{{ $star }}" name="rating" value="{{ $star }}"
                  @checked((int) old('rating', $myReview?->rating ?? 0) === $star) required>
                <label for="product-review-star-{{ $star }}" title="{{ $star }} yıldız" aria-label="{{ $star }} yıldız">★</label>
              @endfor
            </div>
            <small class="review-rating-caption" data-review-caption aria-live="polite">Yıldızlara dokunarak puan verin</small>
          </fieldset>
          <label class="review-text-label" for="review-message">Ürünle ilgili deneyiminiz</label>
          <textarea id="review-message" name="body" maxlength="1000" minlength="15" rows="4"
            placeholder="Ürünü nerede kullandınız? En çok hangi özelliğini beğendiniz?"
            required>{{ old('body', $myReview?->body ?? '') }}</textarea>
          <div class="review-form-bottom">
            <span><x-store-icon name="check" /> Doğrulanmış satın alma</span>
            <button type="submit" class="review-primary-action">{{ $myReview ? 'Değerlendirmemi güncelle' : 'Yorumumu gönder' }}</button>
          </div>
          <p class="review-form-disclaimer">Yorumunuz güvenlik ve içerik kontrolünden sonra yayınlanır. Test siparişleri genel puana dahil edilmez.</p>
        </form>
      @else
        <p class="review-help-text">Bu ürünü değerlendirmek için hesabınızla verdiğiniz siparişin <strong>Tamamlandı</strong> durumunda olması gerekir.</p>
        <a href="{{ route('account.index') }}" class="review-outline-action">Siparişlerimi görüntüle <x-store-icon name="arrow-right" /></a>
      @endif
    @endif
  </div>

  <div class="review-list-head"><h3>Müşteri değerlendirmeleri</h3><span>En yeniler</span></div>
  @forelse($reviews as $review)
    <article class="review-card">
      <div class="review-card-top">
        <span class="review-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($review->public_name, 0, 1)) }}</span>
        <div class="review-card-person">
          <strong>{{ $review->public_name }}</strong>
          @if($review->is_test)<span class="review-test-label">TEST DEĞERLENDİRMESİ</span>@endif
          <small><x-store-icon name="check" /> {{ $review->is_test ? 'Test siparişi' : 'Satın alma doğrulandı' }}</small>
        </div>
        <time datetime="{{ $review->created_at->toDateString() }}">{{ $review->created_at->format('d.m.Y') }}</time>
      </div>
      <div class="review-card-stars" aria-label="{{ $review->rating }} yıldız">
        @for($star = 1; $star <= 5; $star++)<span @class(['is-lit' => $star <= $review->rating]) aria-hidden="true">★</span>@endfor
      </div>
      <p class="review-card-body">{{ $review->body }}</p>
    </article>
  @empty
    <div class="review-empty">
      <span aria-hidden="true">☆</span>
      <strong>Henüz yayınlanmış yorum yok</strong>
      <p>Bu ürünle ilgili ilk gerçek deneyimi paylaşan siz olabilirsiniz.</p>
    </div>
  @endforelse
  @if($reviews->hasPages())
    <div class="review-pagination">{{ $reviews->onEachSide(1)->links() }}</div>
  @endif
</section>
