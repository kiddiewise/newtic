// Progressive enhancement: without JavaScript the product card POST still opens
// the normal cart. JSON responses share the existing server-side cart rules.
const quickSheet = document.querySelector('[data-quick-sheet]');
if (quickSheet) {
  const backdrop = document.querySelector('[data-quick-backdrop]');
  const content = quickSheet.querySelector('[data-quick-content]');
  const closeButton = quickSheet.querySelector('[data-quick-close]');
  const scrollArea = quickSheet.querySelector('[data-quick-scroll]');
  const messageBox = quickSheet.querySelector('[data-quick-message]');
  const live = quickSheet.querySelector('[data-quick-live]');
  const formatter = new Intl.NumberFormat('tr-TR');
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  let lastTrigger = null;
  let activeProductId = null;
  let busy = false;

  const errorMessage = (body, fallback) => {
    const firstError = body?.errors && Object.values(body.errors)[0];
    return (Array.isArray(firstError) ? firstError[0] : firstError)
      || body?.message || fallback;
  };

  const showError = (error) => {
    messageBox.hidden = false;
    messageBox.textContent = error;
    live.textContent = error;
  };

  const requestCart = async (url, data) => {
    const response = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: data,
    });
    let json = null;
    try { json = await response.json(); } catch { /* upstream HTML error */ }
    if (!response.ok || !json?.ok) {
      if (response.status === 419) {
        throw new Error('Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
      }
      throw new Error(errorMessage(json, 'Ürün eklenemedi. Tekrar deneyin.'));
    }
    return json;
  };

  const updateBadges = (count) => {
    document.querySelectorAll('.header-cart-link, .mega-cart-link').forEach((host) => {
      let badge = host.querySelector('.cart-icon-badge');
      if (count <= 0) { badge?.remove(); }
      else {
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'cart-icon-badge';
          host.appendChild(badge);
        }
        badge.textContent = String(count);
      }
      host.setAttribute('aria-label', 'Sepetim, ' + count + ' adet');
    });
    const bottomLabel = document.querySelector('.mobile-nav-cart span');
    if (bottomLabel) bottomLabel.textContent = count ? 'Sepet (' + count + ')' : 'Sepet';
  };

  const render = (data, changedProductId = null, direction = null) => {
    const scrollTop = changedProductId ? scrollArea.scrollTop : 0;
    content.innerHTML = data.html;
    quickSheet.querySelector('[data-quick-count]').textContent = formatter.format(data.count) + ' adet';
    quickSheet.querySelector('[data-quick-total]').textContent = data.subtotal_label;
    messageBox.textContent = '';
    messageBox.hidden = true;
    updateBadges(data.count);

    if (changedProductId) {
      scrollArea.scrollTop = scrollTop;
      const updated = [...content.querySelectorAll('[data-quick-row]')]
        .find((element) => Number(element.dataset.productId) === changedProductId);
      if (updated) {
        updated.classList.add('is-just-updated');
        const action = updated.querySelector(direction === 'minus' ? '[data-quick-minus]' : '[data-quick-plus]');
        if (action && !action.disabled) action.focus({ preventScroll: true });
        else updated.querySelector('[data-quick-quantity]')?.setAttribute('tabindex', '-1');
      }
    } else {
      scrollArea.scrollTop = 0;
    }
    live.textContent = 'Sepette ' + data.count + ' adet ürün var.';
  };

  const open = (trigger) => {
    lastTrigger = trigger;
    quickSheet.inert = false;
    quickSheet.setAttribute('aria-hidden', 'false');
    quickSheet.setAttribute('role', 'dialog');
    quickSheet.setAttribute('aria-modal', 'true');
    document.body.classList.add('quick-cart-open');
    closeButton.focus({ preventScroll: true });
  };

  const close = (restoreFocus = true) => {
    document.body.classList.remove('quick-cart-open');
    quickSheet.inert = true;
    quickSheet.setAttribute('aria-hidden', 'true');
    quickSheet.removeAttribute('role');
    quickSheet.removeAttribute('aria-modal');
    const previous = lastTrigger;
    lastTrigger = null;
    if (restoreFocus && previous?.isConnected) previous.focus({ preventScroll: true });
  };

  document.addEventListener('submit', async (event) => {
    const form = event.target.closest?.('form[data-quick-add]');
    if (!form || !csrf) return; // keep normal submit if AJAX is unavailable
    event.preventDefault();
    if (busy) return;
    busy = true;
    const button = form.querySelector('[type="submit"]');
    button.disabled = true;
    button.classList.add('is-loading');
    const data = new FormData(form);
    activeProductId = Number(form.dataset.quickProduct);
    data.set('quick_anchor_id', String(activeProductId));
    try {
      const response = await requestCart(form.action, data);
      render(response);
      open(button);
    } catch (error) {
      const text = error instanceof Error ? error.message : 'İşlem tamamlanamadı.';
      button.setAttribute('title', text);
      // Error is visible even if the sheet was never opened.
      let errorNode = form.closest('.product-card')?.querySelector('.quick-cart-card-error');
      if (!errorNode) {
        errorNode = document.createElement('p');
        errorNode.className = 'quick-cart-card-error';
        errorNode.setAttribute('role', 'alert');
        form.closest('.product-card')?.querySelector('.product-card-content')?.appendChild(errorNode);
      }
      if (errorNode) errorNode.textContent = text;
    } finally {
      busy = false;
      button.disabled = false;
      button.classList.remove('is-loading');
    }
  });

  quickSheet.addEventListener('click', async (event) => {
    const button = event.target.closest?.('[data-quick-plus], [data-quick-minus]');
    if (!button || busy) return;
    const row = button.closest('[data-quick-row]');
    const productId = Number(row?.dataset.productId);
    const current = Number(row?.dataset.quantity);
    const max = Number(row?.dataset.stock);
    const plus = button.hasAttribute('data-quick-plus');
    const next = current + (plus ? 1 : -1);
    if (!productId || next < 0 || next > Math.min(999, max)) return;

    busy = true;
    const allButtons = [...quickSheet.querySelectorAll('[data-quick-plus], [data-quick-minus]')];
    allButtons.forEach((item) => { item.disabled = true; });
    const data = new FormData();
    data.set('_token', csrf || '');
    data.set('quick_anchor_id', String(activeProductId ?? productId));
    let endpoint = row.dataset.updateUrl;
    if (current === 0 && plus) {
      endpoint = row.dataset.addUrl;
      data.set('items[' + productId + ']', '1');
    } else {
      data.set('_method', 'PATCH');
      data.set('quantity', String(next));
    }
    try {
      const response = await requestCart(endpoint, data);
      render(response, productId, plus ? 'plus' : 'minus');
    } catch (error) {
      showError(error instanceof Error ? error.message : 'Miktar güncellenemedi.');
      allButtons.forEach((item) => {
        const r = item.closest('[data-quick-row]');
        const q = Number(r?.dataset.quantity);
        const stock = Number(r?.dataset.stock);
        const isPlus = item.hasAttribute('data-quick-plus');
        item.disabled = isPlus ? q >= Math.min(stock, 999) : q <= 0;
      });
    } finally {
      busy = false;
    }
  });

  closeButton?.addEventListener('click', () => close());
  backdrop?.addEventListener('click', () => close());
  document.addEventListener('keydown', (event) => {
    if (!document.body.classList.contains('quick-cart-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault(); close();
    } else if (event.key === 'Tab') {
      const tabbables = [...quickSheet.querySelectorAll('button:not([disabled]),a[href]')]
        .filter((element) => element.getClientRects().length > 0);
      if (!tabbables.length) return;
      const first = tabbables[0], last = tabbables[tabbables.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault(); last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault(); first.focus();
      }
    }
  });
  window.addEventListener('pagehide', () => {
    if (document.body.classList.contains('quick-cart-open')) close(false);
  });
  window.addEventListener('pageshow', (event) => {
    const nav = performance.getEntriesByType('navigation')[0];
    if ((event.persisted || nav?.type === 'back_forward')
      && document.querySelector('[data-quick-add]')) {
      window.location.reload();
    }
  });
}
