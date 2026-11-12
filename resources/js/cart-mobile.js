// Progressive enhancement: cart works with ordinary POST/PATCH forms without JavaScript.
// Never display an outdated cart from the browser back/forward cache.
if (document.body.classList.contains('is-cart-page')) {
  window.addEventListener('pageshow', (event) => {
    const navigation = performance.getEntriesByType('navigation')[0];
    if (event.persisted || navigation?.type === 'back_forward') {
      window.location.reload();
    }
  });
}
const cartForms = [...document.querySelectorAll('[data-cart-update-form]')];
if (cartForms.length) {
  document.body.classList.add('cart-js-ready');
  for (const form of cartForms) {
    const input = form.querySelector('input[name="quantity"]');
    const minus = form.querySelector('[data-cart-minus]');
    const plus = form.querySelector('[data-cart-plus]');
    if (!input) continue;
    const min = Math.max(1, Number(input.min) || 1);
    const max = Math.max(min, Number(input.max) || 999);
    const submit = (next) => {
      const current = Number(input.value) || min;
      const bounded = Math.min(max, Math.max(min, Math.trunc(next)));
      if (current === bounded) return;
      input.value = bounded;
      form.requestSubmit();
    };
    minus?.addEventListener('click', () => submit((Number(input.value) || min) - 1));
    plus?.addEventListener('click', () => submit((Number(input.value) || min) + 1));
    input.addEventListener('change', () => form.requestSubmit());
  }
}

const cartSheet = document.querySelector('[data-cart-summary-sheet]');
if (cartSheet) {
  const backdrop = document.querySelector('[data-cart-summary-backdrop]');
  const openers = [...document.querySelectorAll('[data-cart-summary-open]')];
  const closeButton = cartSheet.querySelector('[data-cart-summary-close]');
  let activeTrigger = null;
  cartSheet.inert = true;

  const open = (trigger) => {
    if (!matchMedia('(max-width: 760px)').matches) return;
    activeTrigger = trigger;
    document.body.classList.add('cart-summary-open');
    cartSheet.inert = false;
    cartSheet.setAttribute('aria-hidden', 'false');
    cartSheet.setAttribute('role', 'dialog');
    cartSheet.setAttribute('aria-modal', 'true');
    openers.forEach((button) => button.setAttribute('aria-expanded', 'true'));
    closeButton?.focus({ preventScroll: true });
  };

  const close = () => {
    document.body.classList.remove('cart-summary-open');
    cartSheet.inert = true;
    cartSheet.setAttribute('aria-hidden', 'true');
    cartSheet.removeAttribute('role');
    cartSheet.removeAttribute('aria-modal');
    openers.forEach((button) => button.setAttribute('aria-expanded', 'false'));
    activeTrigger?.focus({ preventScroll: true });
    activeTrigger = null;
  };

  openers.forEach((button) => button.addEventListener('click', () =>
    document.body.classList.contains('cart-summary-open') ? close() : open(button)
  ));
  closeButton?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);
  document.addEventListener('keydown', (event) => {
    if (!document.body.classList.contains('cart-summary-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
    if (event.key === 'Tab') {
      const tabbables = [...cartSheet.querySelectorAll('button:not(:disabled),a[href]')].filter((element) => element.getClientRects().length > 0);
      if (tabbables.length === 0) return;
      if (event.shiftKey && document.activeElement === tabbables[0]) {
        event.preventDefault();
        tabbables[tabbables.length - 1].focus();
      } else if (!event.shiftKey && document.activeElement === tabbables[tabbables.length - 1]) {
        event.preventDefault();
        tabbables[0].focus();
      }
    }
  });
  matchMedia('(min-width: 761px)').addEventListener('change', (event) => {
    if (event.matches && document.body.classList.contains('cart-summary-open')) close();
  });

  // The cart header link navigates to the storefront directly.
  // Never traverse browser history here: the previous entry can be checkout.
}
