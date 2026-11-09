<div class="quick-cart-backdrop" data-quick-backdrop aria-hidden="true"></div>
<section id="quick-cart-sheet" class="quick-cart-sheet" data-quick-sheet aria-hidden="true" inert aria-labelledby="quick-cart-heading">
  <div class="quick-cart-handle" aria-hidden="true"></div>
  <div class="quick-cart-heading">
    <div class="quick-cart-heading-info">
      <span class="quick-cart-check" aria-hidden="true">
        <svg viewBox="0 0 24 24"><path d="m5 12 4.5 4.5L19 7"/></svg>
      </span>
      <div>
        <span class="quick-cart-eyebrow">SEPETİNE EKLENDİ</span>
        <h2 id="quick-cart-heading">Ürünlerini düzenle</h2>
      </div>
    </div>
    <button type="button" class="quick-cart-close" data-quick-close aria-label="Hızlı sepet panelini kapat">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 5l14 14M19 5 5 19"/></svg>
    </button>
  </div>
  <div class="quick-cart-scroll" data-quick-scroll>
    <div class="quick-cart-message" data-quick-message role="alert" hidden></div>
    <div data-quick-content></div>
  </div>
  <div class="quick-cart-footer">
    <div class="quick-cart-footer-total">
      <span>Sepet tutarı <small data-quick-count>0 adet</small></span>
      <strong data-quick-total>0,00 ₺</strong>
    </div>
    <a class="quick-cart-view-cart" href="{{ route('cart.index') }}">Sepetime git
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m9 5 7 7-7 7"/></svg>
    </a>
  </div>
  <div class="quick-cart-live sr-only" aria-live="polite" data-quick-live></div>
</section>
