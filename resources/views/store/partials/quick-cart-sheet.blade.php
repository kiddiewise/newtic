<div class="quick-cart-backdrop" data-quick-backdrop aria-hidden="true"></div>
<section id="quick-cart-sheet" class="quick-cart-sheet" data-quick-sheet aria-hidden="true" inert aria-labelledby="quick-cart-heading">
  <div class="quick-cart-handle" aria-hidden="true"></div>
  <div class="quick-cart-heading">
    <div class="quick-cart-heading-info">
      <span class="quick-cart-check" aria-hidden="true">
        <x-store-icon name="check" />
      </span>
      <div>
        <span class="quick-cart-eyebrow">SEPETİNE EKLENDİ</span>
        <h2 id="quick-cart-heading">Ürünlerini düzenle</h2>
      </div>
    </div>
    <button type="button" class="quick-cart-close" data-quick-close aria-label="Hızlı sepet panelini kapat">
      <x-store-icon name="x" />
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
      <x-store-icon name="chevron-right" />
    </a>
  </div>
  <div class="quick-cart-live sr-only" aria-live="polite" data-quick-live></div>
</section>
