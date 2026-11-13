// Product-only mobile interaction; the POST /sepet/ekle endpoint validates price, stock and product IDs.
const pdpSheet = document.querySelector('[data-product-sheet]');
if (pdpSheet) {
  const orderInputs = [...pdpSheet.querySelectorAll('[data-order-quantity]')];
  const optionSelects = [...pdpSheet.querySelectorAll('[data-product-option]')];
  const mirrorSelects = [...document.querySelectorAll('[data-option-mirror]')];
  const optionHelp = [...document.querySelectorAll('[data-option-help]')];
  const cartForm = document.getElementById('pdp-cart-form');
  const optionHint = pdpSheet.querySelector('[data-variant-hint]');
  const orderSummary = pdpSheet.querySelector('[data-order-summary]');
  const addButtons = [...document.querySelectorAll('[data-cart-add]')];
  const sticky = document.querySelector('[data-sticky-bar]');
  const backdrop = document.querySelector('[data-sheet-backdrop]');
  const stickySelection = sticky?.querySelector('[data-sticky-selection]');
  const stickyTotal = sticky?.querySelector('[data-sticky-total]');
  const quickMinus = sticky?.querySelector('[data-quick-minus]');
  const quickPlus = sticky?.querySelector('[data-quick-plus]');
  const quickValue = sticky?.querySelector('[data-quick-qty]');
  const desktopMinus = document.querySelector('[data-desktop-minus]');
  const desktopPlus = document.querySelector('[data-desktop-plus]');
  const desktopValue = document.querySelector('[data-desktop-qty]');
  const desktopSummary = document.querySelector('[data-desktop-summary]');
  const opens = [...document.querySelectorAll('[data-sheet-open],[data-sheet-open-inline]')];
  const closes = [...pdpSheet.querySelectorAll('[data-sheet-close]')];
  const currentInput = orderInputs.find((field) =>
    field.closest('.pdp-order-item')?.classList.contains('is-current')
  );
  const currentOptionLabel = currentInput?.closest('.pdp-order-item')
    ?.querySelector('.pdp-variant-field>span')?.firstChild?.textContent?.trim() || 'Ürün seçeneği';
  if (sticky?.querySelector('.has-variant')) document.body.classList.add('pdp-has-variant');
  const fmt = new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' });
  let lastTrigger = null;
  const mobileQuery = matchMedia('(max-width: 760px)');
  const sessionKey = 'newtic-pdp-quantities:' + pdpSheet.dataset.categoryId;

  // Reveal only the gram-selection ribbon on downward scrolling; hide it on
  // upward scrolling. Never slide away the quantity/variant/cart controls.
  // RAF + a distance threshold prevent micro-scroll and browser UI jitter.
  const gramRibbon = sticky?.querySelector('[data-scroll-ribbon]');
  if (gramRibbon) {
    let lastScrollY = Math.max(0, window.scrollY);
    let directionDistance = 0;
    let lastDirection = 0;
    let framePending = false;
    let ribbonIsVisible = false;

    const setRibbonVisibility = (visible) => {
      if (ribbonIsVisible === visible) return;
      ribbonIsVisible = visible;
      gramRibbon.classList.toggle('is-scroll-visible', visible);
      gramRibbon.setAttribute('aria-hidden', String(!visible));
      gramRibbon.inert = !visible;
    };

    gramRibbon.inert = true;
    gramRibbon.setAttribute('aria-hidden', 'true');

    const processRibbonScroll = () => {
      framePending = false;
      const scrollY = Math.max(0, window.scrollY);
      const distance = scrollY - lastScrollY;
      lastScrollY = scrollY;

      if (!mobileQuery.matches || scrollY < 85 || document.body.classList.contains('pdp-sheet-open')) {
        directionDistance = 0;
        lastDirection = 0;
        setRibbonVisibility(false);
        return;
      }
      if (Math.abs(distance) < 1) return;
      const direction = Math.sign(distance);
      if (direction !== lastDirection) directionDistance = 0;
      lastDirection = direction;
      directionDistance += distance;
      if (directionDistance >= 16) {
        setRibbonVisibility(true);
        directionDistance = 0;
      } else if (directionDistance <= -16) {
        setRibbonVisibility(false);
        directionDistance = 0;
      }
    };

    const scheduleRibbonScroll = () => {
      if (framePending) return;
      framePending = true;
      window.requestAnimationFrame(processRibbonScroll);
    };
    window.addEventListener('scroll', scheduleRibbonScroll, { passive: true });
    window.addEventListener('pageshow', () => {
      lastScrollY = Math.max(0, window.scrollY);
      lastDirection = 0;
      directionDistance = 0;
      setRibbonVisibility(false);
    });
    mobileQuery.addEventListener('change', () => {
      lastScrollY = Math.max(0, window.scrollY);
      lastDirection = 0;
      directionDistance = 0;
      if (!mobileQuery.matches) setRibbonVisibility(false);
    });
  }

  const clamp = (field, raw) => {
    const max = Number(field.max);
    const qty = Number(raw);
    return Number.isFinite(qty)
      ? Math.max(0, Math.min(Number.isFinite(max) ? max : 0, Math.trunc(qty)))
      : 0;
  };

  // Picker quantities are only a draft. The cart is the sole durable source of
  // truth, so old values must not outlive add/remove/clear actions.
  try { sessionStorage.removeItem(sessionKey); }
  catch { /* Optional browser storage may be unavailable. */ }

  const update = () => {
    let quantity = 0;
    let cents = 0;
    let optionMissing = false;
    let missingProduct = '';
    let firstMissingOption = null;
    orderInputs.forEach((field) => {
      const number = clamp(field, field.value);
      const option = optionSelects.find(select => select.dataset.productId === field.dataset.productId);
      if (number > 0 && option && !option.value) {
        optionMissing = true;
        if (!missingProduct) missingProduct = field.closest('.pdp-order-item')?.querySelector('.order-item-info a')?.textContent?.trim() || 'Seçili ürün';
        firstMissingOption ||= option;
        option.classList.add('is-required-choice');
        option.setAttribute('aria-invalid', 'true');
      } else if (option) {
        option.classList.remove('is-required-choice');
        option.removeAttribute('aria-invalid');
      }
      if (field.value !== String(number)) field.value = String(number);
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
    if (desktopSummary) desktopSummary.textContent = 'Seçilen toplam: ' + quantity + ' adet · ' + amount;
    // The add button stays actionable when a size is missing: tapping it
    // takes the customer to the exact selector instead of silently failing.
    addButtons.forEach((button) => {
      button.disabled = quantity === 0;
      button.dataset.actionState = quantity === 0 ? 'empty' : (optionMissing ? 'needs-option' : 'ready');
      if (optionMissing && quantity > 0) {
        button.setAttribute('aria-disabled', 'true');
        button.dataset.needsOption = 'true';
      } else {
        button.removeAttribute('aria-disabled');
        delete button.dataset.needsOption;
      }
    });
    if (optionHint) optionHint.textContent = optionMissing
      ? missingProduct + ' için iğne kalınlığını seçin.'
      : '';
    if (stickySelection) stickySelection.textContent = optionMissing
      ? 'Seçim gerekli · ' + quantity + ' adet'
      : quantity + ' adet seçildi';

    const currentOption = currentInput
      ? optionSelects.find((select) => select.dataset.productId === currentInput.dataset.productId)
      : null;
    mirrorSelects.forEach((mirror) => {
      const source = optionSelects.find((select) => select.dataset.productId === mirror.dataset.productId);
      if (!source) return;
      if (mirror.value !== source.value) mirror.value = source.value;
      const missing = Boolean(source === firstMissingOption || (currentInput && source === currentOption
        && Number(currentInput.value) > 0 && !source.value));
      mirror.classList.toggle('is-required-choice', missing);
      if (missing) mirror.setAttribute('aria-invalid', 'true');
      else mirror.removeAttribute('aria-invalid');
    });
    optionHelp.forEach((help) => {
      const selected = currentOption?.selectedOptions?.[0]?.textContent?.trim();
      const isStickyHelp = help.dataset.optionHelp === 'sticky';
      const currentMissing = currentInput && Number(currentInput.value) > 0 && currentOption && !currentOption.value;
      if (currentMissing) {
        help.textContent = isStickyHelp
          ? currentOptionLabel + ' seçin'
          : 'Zorunlu: Sepete eklemek için ' + currentOptionLabel.toLocaleLowerCase('tr-TR') + ' seçin.';
        help.dataset.state = 'required';
      } else if (quantity === 0 && currentInput && Number(currentInput.max) === 0) {
        help.textContent = isStickyHelp ? 'Ürün stokta yok' : 'Bu ürün stokta yok. Diğer gramajları inceleyin.';
        help.dataset.state = 'neutral';
      } else if (quantity === 0) {
        help.textContent = isStickyHelp ? 'Adet seçin' : 'Sepete eklemek için ürün adedini seçin.';
        help.dataset.state = 'neutral';
      } else if (optionMissing) {
        help.textContent = isStickyHelp
          ? 'Diğer ürünün seçeneği eksik'
          : 'Diğer ürünün seçeneğini Seçenekler bölümünden belirleyin.';
        help.dataset.state = 'required';
      } else if (selected && currentOption?.value) {
        help.textContent = isStickyHelp
          ? selected + ' seçildi'
          : selected + ' seçildi · Sepete eklemeye hazır';
        help.dataset.state = 'ready';
      } else {
        help.textContent = isStickyHelp
          ? currentOptionLabel + ' seçin'
          : 'Sepete eklemek için ' + currentOptionLabel.toLocaleLowerCase('tr-TR') + ' belirleyin.';
        help.dataset.state = 'neutral';
      }
    });
    if (stickyTotal) stickyTotal.textContent = amount;
    if (currentInput) {
      const value = Number(currentInput.value);
      if (quickValue) quickValue.textContent = String(value);
      if (desktopValue) desktopValue.textContent = String(value);
      if (desktopMinus) desktopMinus.disabled = value <= 0;
      if (desktopPlus) desktopPlus.disabled = value >= Number(currentInput.max);
      if (quickMinus) quickMinus.disabled = value <= 0;
      if (quickPlus) quickPlus.disabled = value >= Number(currentInput.max);
    }
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
  optionSelects.forEach((select) => select.addEventListener('change', update));
  mirrorSelects.forEach((mirror) => {
    mirror.addEventListener('change', () => {
      const source = optionSelects.find((select) => select.dataset.productId === mirror.dataset.productId);
      if (source) source.value = mirror.value;
      update();
    });
  });
  pdpSheet.querySelectorAll('[data-step-minus],[data-step-plus]').forEach((button) => {
    button.addEventListener('click', () => {
      const field = button.closest('.pdp-stepper')?.querySelector('[data-order-quantity]');
      adjust(field, button.hasAttribute('data-step-plus') ? 1 : -1);
    });
  });
  quickMinus?.addEventListener('click', () => adjust(currentInput, -1));
  quickPlus?.addEventListener('click', () => adjust(currentInput, 1));
  desktopMinus?.addEventListener('click', () => adjust(currentInput, -1));
  desktopPlus?.addEventListener('click', () => adjust(currentInput, 1));
  update();

  // Browser back/forward cache can restore the old form DOM after a cart
  // mutation. Always zero the temporary draft on history traversal.
  window.addEventListener('pageshow', (event) => {
    const navigation = performance.getEntriesByType('navigation')[0];
    if (event.persisted || navigation?.type === 'back_forward') {
      try { sessionStorage.removeItem(sessionKey); } catch { /* optional */ }
      // Re-fetch the page from Laravel: previous selections, cart badge and
      // stock may all be stale after returning via browser history.
      window.location.reload();
    }
  });

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

  // A missing option must never submit an ambiguous item to the server.
  // The actual form select inside the category sheet remains the only named
  // value; the desktop and sticky selectors mirror that single source.
  cartForm?.addEventListener('submit', (event) => {
    const missing = orderInputs.find((field) => {
      const select = optionSelects.find((item) => item.dataset.productId === field.dataset.productId);
      return Number(field.value) > 0 && select && !select.value;
    });
    if (!missing) return;
    event.preventDefault();
    event.stopImmediatePropagation();
    const source = optionSelects.find((item) => item.dataset.productId === missing.dataset.productId);
    const isCurrent = currentInput && currentInput.dataset.productId === missing.dataset.productId;
    let target = null;

    if (isCurrent) {
      const location = mobileQuery.matches ? 'sticky' : 'desktop';
      target = mirrorSelects.find((item) => item.dataset.optionLocation === location);
    }
    if (!target) {
      open(event.submitter || document.querySelector('[data-sheet-open]'));
      target = source;
      if (mobileQuery.matches) source?.closest('.pdp-order-item')?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
    target?.focus({ preventScroll: mobileQuery.matches });
    target?.classList.add('is-required-choice');
    target?.setAttribute('aria-invalid', 'true');
    if (optionHint) optionHint.textContent = 'Sepete eklemek için ' + currentOptionLabel.toLocaleLowerCase('tr-TR') + ' belirleyin.';
  }, true);

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

// Desktop arrows, mobile swipe and two-finger transient zoom share one gallery state.
import { initProductGallery } from './product-gallery.js';
initProductGallery();
