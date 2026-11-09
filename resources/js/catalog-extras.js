// Simple accessible product image gallery — original uploads are served from public storage.
document.querySelectorAll('[data-gallery-thumb]').forEach((button) => {
  button.addEventListener('click', () => {
    const hero = document.querySelector('[data-product-main-image]');
    if (!hero) return;
    hero.src = button.dataset.imageUrl;
    document.querySelectorAll('[data-gallery-thumb]').forEach((other) => {
      other.setAttribute('aria-pressed', String(other === button));
    });
  });
});

// The backend independently validates sale_price < regular_price.
const regularInput = document.querySelector('[data-price-regular]');
const saleInput = document.querySelector('[data-price-sale]');
const preview = document.querySelector('[data-discount-preview]');
if (regularInput && saleInput && preview) {
  const update = () => {
    const regular = Number(regularInput.value);
    const sale = Number(saleInput.value);
    preview.classList.remove('is-invalid');
    if (!saleInput.value) {
      preview.textContent = 'İndirimli fiyat boşsa ürün normal fiyatından satılır.';
    } else if (!(regular > 0 && sale > 0 && sale < regular)) {
      preview.textContent = 'İndirimli fiyat, normal fiyattan düşük ve sıfırdan büyük olmalıdır.';
      preview.classList.add('is-invalid');
    } else {
      const percent = Math.max(1, Math.min(99, Math.round((1 - sale / regular) * 100)));
      preview.textContent = 'Mağazada otomatik gösterilecek indirim: -%' + percent;
    }
  };
  regularInput.addEventListener('input', update);
  saleInput.addEventListener('input', update);
  update();
}
