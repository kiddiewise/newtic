// Product-only mobile interaction; the POST /sepet/ekle endpoint validates price, stock and product IDs.
const pdpSheet = document.querySelector('[data-product-sheet]');
if (pdpSheet) {
  const orderInputs = [...pdpSheet.querySelectorAll('[data-order-quantity]')];
  const orderSummary = pdpSheet.querySelector('[data-order-summary]');
  const addButtons = [...document.querySelectorAll('[data-cart-add]')];
  const sticky = document.querySelector('[data-sticky-bar]');
  const backdrop = document.querySelector('[data-sheet-backdrop]');
  const stickySelection = sticky?.querySelector('[data-sticky-selection]');
  const stickyTotal = sticky?.querySelector('[data-sticky-total]');
  const quickMinus = sticky?.querySelector('[data-quick-minus]');
  const quickPlus = sticky?.querySelector('[data-quick-plus]');
  const quickValue = sticky?.querySelector('[data-quick-qty]');
  const opens = [...document.querySelectorAll('[data-sheet-open],[data-sheet-open-inline]')];
  const closes = [...pdpSheet.querySelectorAll('[data-sheet-close]')];
  const currentInput = orderInputs.find((field) =>
    field.closest('.pdp-order-item')?.classList.contains('is-current')
  );
  const fmt = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
  let lastTrigger = null;
  const mobileQuery = matchMedia('(max-width: 760px)');
  const sessionKey = 'newtic-pdp-quantities:' + pdpSheet.dataset.categoryId;

  const clamp = (field, raw) => {
    const max = Number(field.max);
    const qty = Number(raw);
    return Number.isFinite(qty)
      ? Math.max(0, Math.min(Number.isFinite(max) ? max : 0, Math.trunc(qty)))
      : 0;
  };

  let saved = null;
  try {
    const raw = sessionStorage.getItem(sessionKey);
    if (raw) {
      const candidate = JSON.parse(raw);
      if (candidate && typeof candidate === 'object' && !Array.isArray(candidate)) saved = candidate;
    }
  } catch { /* Browser blocks storage; selectors still work normally. */ }

  if (saved) {
    orderInputs.forEach((field) => {
      const key = field.dataset.productId;
      if (Object.prototype.hasOwnProperty.call(saved, key)) {
        field.value = clamp(field, saved[key]);
      }
    });
  }

  const update = () => {
    let quantity = 0;
    let cents = 0;
    const quantities = {};
    orderInputs.forEach((field) => {
      const number = clamp(field, field.value);
      if (field.value !== String(number)) field.value = String(number);
      quantities[field.dataset.productId] = number;
      quantity += number;
      cents += Math.round(Number(field.dataset.price || 0) * 100) * number;
      const controls = field.closest('.pdp-stepper');
      const decrease = controls?.querySelector('[data-step-minus]');
      const increase = controls?.querySelector('[data-step-plus]');
      if (decrease) decrease.disabled = number <= 0;
      if (increase) increase.disabled = number >= Number(field.max);
    });
    const amount = fmt.format(cents / 100);
    if (orderSummary) orderSummary.textContent = quantity + ' adet · ' + amount;
    addButtons.forEach((button) => { button.disabled = quantity === 0; });
    if (stickySelection) stickySelection.textContent = quantity + ' adet seçildi';
    if (stickyTotal) stickyTotal.textContent = amount;
    if (currentInput) {
      const value = Number(currentInput.value);
      if (quickValue) quickValue.textContent = String(value);
      if (quickMinus) quickMinus.disabled = value <= 0;
      if (quickPlus) quickPlus.disabled = value >= Number(currentInput.max);
    }
    try { sessionStorage.setItem(sessionKey, JSON.stringify(quantities)); }
    catch { /* Optional local session persistence. */ }
  };

  const adjust = (field, amount) => {
    if (!field) return;
    field.value = String(clamp(field, Number(field.value) + amount));
    update();
  };
  orderInputs.forEach((field) => {
    field.addEventListener('input', update);
    field.addEventListener('change', update);
  });
  pdpSheet.querySelectorAll('[data-step-minus],[data-step-plus]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = button.closest('.pdp-stepper')?.querySelector('[data-order-quantity]');
      adjust(field, button.hasAttribute('data-step-plus') ? 1 : -1);
    });
  });
  quickMinus?.addEventListener('click', () => adjust(currentInput, -1));
  quickPlus?.addEventListener('click', () => adjust(currentInput, 1));
  update();

  const close = () => {
    document.body.classList.remove('pdp-sheet-open');
    pdpSheet.removeAttribute('role');
    pdpSheet.removeAttribute('aria-modal');
    opens.forEach((el) => el.setAttribute('aria-expanded', 'false'));
    const restore = lastTrigger;
    lastTrigger = null;
    if (restore && mobileQuery.matches) restore.focus({ preventScroll: true });
  };
  const open = (trigger) => {
    if (!mobileQuery.matches) {
      pdpSheet.scrollIntoView({ behavior: 'smooth', block: 'start' });
      return;
    }
    lastTrigger = trigger;
    document.body.classList.add('pdp-sheet-open');
    pdpSheet.setAttribute('role', 'dialog');
    pdpSheet.setAttribute('aria-modal', 'true');
    opens.forEach((el) => el.setAttribute('aria-expanded', 'true'));
    const firstClose = pdpSheet.querySelector('[data-sheet-close]');
    firstClose?.focus({ preventScroll: true });
  };
  opens.forEach((button) => {
    button.setAttribute('aria-controls', 'pdp-picker');
    button.setAttribute('aria-expanded', 'false');
    button.addEventListener('click', () => open(button));
  });
  closes.forEach((button) => button.addEventListener('click', close));
  backdrop?.addEventListener('click', close);
  window.addEventListener('keydown', (event) => {
    if (!document.body.classList.contains('pdp-sheet-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
    if (event.key === 'Tab') {
      const focusables = [...pdpSheet.querySelectorAll('button:not(:disabled),a[href],input:not(:disabled)')]
        .filter((el) => el.getClientRects().length > 0);
      if (!focusables.length) return;
      const first = focusables[0], last = focusables[focusables.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
  mobileQuery.addEventListener('change', () => {
    if (!mobileQuery.matches && document.body.classList.contains('pdp-sheet-open')) close();
  });

  const price = document.querySelector('[data-detail-price-anchor]');
  const updatePricePosition = () => {
    if (!price || !sticky) return;
    const bottom = price.getBoundingClientRect().bottom;
    sticky.classList.toggle('is-price-visible', bottom < 68);
  };
  window.addEventListener('scroll', updatePricePosition, { passive: true });
  window.addEventListener('resize', updatePricePosition, { passive: true });
  updatePricePosition();

  const back = document.querySelector('[data-product-back]');
  back?.addEventListener('click', (event) => {
    try {
      if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) {
        event.preventDefault();
        history.back();
      }
    } catch { /* Normal category link remains the fallback. */ }
  });

  const description = document.querySelector('[data-description-content]');
  const toggle = document.querySelector('[data-description-toggle]');
  if (description && toggle) {
    const checkTextHeight = () => {
      if (description.classList.contains('is-expanded')) return;
      const needed = description.scrollHeight > description.clientHeight + 3;
      toggle.hidden = !needed;
    };
    toggle.addEventListener('click', () => {
      const expanded = description.classList.toggle('is-expanded');
      toggle.setAttribute('aria-expanded', String(expanded));
      toggle.innerHTML = expanded ? 'Daha az göster <span aria-hidden="true">⌃</span>' : 'Daha fazla göster <span aria-hidden="true">⌄</span>';
    });
    requestAnimationFrame(checkTextHeight);
  }
}

const gallery = document.querySelector('[data-product-gallery]');
if (gallery) {
  const main = gallery.querySelector('[data-product-main-image]');
  const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
  const count = gallery.querySelector('[data-gallery-current]');
  const images = thumbs.length ? thumbs.map((el) => el.dataset.imageUrl) : [main?.src];
  let index = 0;
  const show = (next) => {
    if (!main || !images.length) return;
    index = (next + images.length) % images.length;
    main.src = images[index];
    if (count) count.textContent = String(index + 1);
    thumbs.forEach((el, i) => el.setAttribute('aria-pressed', String(i === index)));
  };
  thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => show(i)));

  const swipeArea = gallery.querySelector('[data-gallery-swipe]');
  let start = null;
  swipeArea?.addEventListener('pointerdown', (event) => {
    if (event.pointerType !== 'touch' && event.pointerType !== 'pen') return;
    start = { x: event.clientX, y: event.clientY };
  }, { passive: true });
  swipeArea?.addEventListener('pointerup', (event) => {
    if (!start) return;
    const dx = event.clientX - start.x;
    const dy = event.clientY - start.y;
    start = null;
    if (Math.abs(dx) > 42 && Math.abs(dx) > Math.abs(dy) * 1.3 && images.length > 1) {
      show(index + (dx < 0 ? 1 : -1));
    }
  }, { passive: true });
  swipeArea?.addEventListener('pointercancel', () => { start = null; });
}
