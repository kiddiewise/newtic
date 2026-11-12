/**
 * A checkout-to-cart link must not append a second cart page on top of
 * checkout, otherwise the cart's "back" can lead straight to checkout.
 *
 * If checkout was opened from cart, traverse back to that existing entry.
 * When the referrer is unavailable (privacy, direct URL, reload, other tab),
 * REPLACE checkout with cart so browser Back cannot re-open checkout.
 */
export function checkoutCartNavigation(referrer, cartHref, historyLength) {
  if (Number(historyLength) <= 1) return 'replace';
  try {
    const cart = new URL(cartHref);
    const previous = new URL(referrer);
    const path = (value) => value.replace(/\/+$/, '') || '/';
    return previous.origin === cart.origin && path(previous.pathname) === path(cart.pathname)
      ? 'back' : 'replace';
  } catch {
    return 'replace';
  }
}

export function installCheckoutCartLinks() {
  document.querySelectorAll('a[data-checkout-return-cart]').forEach((link) => {
    link.addEventListener('click', (event) => {
      // Respect open-in-new-tab, download and browser context-menu behavior.
      if (event.defaultPrevented || event.button !== 0 || event.metaKey ||
          event.ctrlKey || event.shiftKey || event.altKey || link.hasAttribute('download') ||
          (link.target && link.target !== '_self')) return;
      event.preventDefault();
      const method = checkoutCartNavigation(document.referrer, link.href, window.history.length);
      if (method === 'back') {
        window.history.back();
      } else {
        window.location.replace(link.href);
      }
    });
  });
}
