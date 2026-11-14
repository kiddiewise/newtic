// Polaris immersive mobile navigation. Native <details> remains functional without JS.
const megaMenu = document.querySelector('[data-mega-menu]');
if (megaMenu) {
  const trigger = megaMenu.querySelector(':scope > summary');
  const panel = megaMenu.querySelector('[data-mega-panel]');
  const closeButton = megaMenu.querySelector('[data-mega-close]');
  const scrollLayer = megaMenu.querySelector('[data-mega-scroll]');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let closeTimer = null;

  document.body.classList.add('mega-motion');

  const finishClose = (restoreFocus = true) => {
    if (closeTimer) window.clearTimeout(closeTimer);
    closeTimer = null;
    megaMenu.open = false;
    megaMenu.classList.remove('is-visible');
    document.body.classList.remove('mega-menu-open');
    trigger?.setAttribute('aria-expanded', 'false');
    if (restoreFocus) trigger?.focus({ preventScroll: true });
  };

  const openMenu = () => {
    if (closeTimer) window.clearTimeout(closeTimer);
    closeTimer = null;
    megaMenu.open = true;
    scrollLayer.scrollTop = 0;
    document.body.classList.add('mega-menu-open');
    trigger?.setAttribute('aria-expanded', 'true');
    window.requestAnimationFrame(() => {
      megaMenu.classList.add('is-visible');
      closeButton?.focus({ preventScroll: true });
    });
  };

  const closeMenu = (restoreFocus = true) => {
    if (!megaMenu.open || closeTimer) return;
    megaMenu.classList.remove('is-visible');
    if (reduceMotion.matches) {
      finishClose(restoreFocus);
      return;
    }
    closeTimer = window.setTimeout(() => finishClose(restoreFocus), 390);
  };

  trigger?.addEventListener('click', (event) => {
    event.preventDefault();
    if (megaMenu.open && !closeTimer) {
      closeMenu();
    } else {
      openMenu();
    }
  });

  closeButton?.addEventListener('click', () => closeMenu());

  // A single expanded main category at a time keeps the long menu readable.
  const topGroups = [...megaMenu.querySelectorAll('.mega-tree-group[data-category-depth="0"]')];
  topGroups.forEach((group) => {
    group.addEventListener('toggle', () => {
      if (!group.open) return;
      topGroups.forEach((other) => {
        if (other !== group) other.open = false;
      });
    });
  });

  document.addEventListener('keydown', (event) => {
    if (!megaMenu.open) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      closeMenu();
      return;
    }
    if (event.key !== 'Tab' || closeTimer) return;
    const focusables = [...panel.querySelectorAll('button:not([disabled]), a[href], summary')]
      .filter((element) => element.getClientRects().length > 0);
    if (!focusables.length) return;
    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    }
  });

  // Avoid a stale, locked overlay when restoring a page from browser history.
  window.addEventListener('pagehide', () => {
    if (megaMenu.open) finishClose(false);
  });
}

// An active category remains visible in the horizontally scrollable pill bar.
const categoryPills = document.querySelector('.mobile-category-pills');
const activeCategoryPill = categoryPills?.querySelector('.mobile-pill.is-active');
if (categoryPills && activeCategoryPill) {
  window.requestAnimationFrame(() => {
    const targetLeft = activeCategoryPill.offsetLeft
      - categoryPills.offsetLeft
      - (categoryPills.clientWidth - activeCategoryPill.clientWidth) / 2;
    categoryPills.scrollLeft = Math.max(0, targetLeft);
  });
}
