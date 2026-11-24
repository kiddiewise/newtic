const nav = document.querySelector('[data-admin-mobile-nav]');
const dialog = document.querySelector('[data-admin-more-dialog]');
if (nav && dialog) {
  const mobile = matchMedia('(max-width: 900px)');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const opener = nav.querySelector('[data-admin-more-open]');
  const closer = dialog.querySelector('[data-admin-more-close]');
  let closing = false;
  let timer = null;
  const closeNow = () => {
    clearTimeout(timer);
    if (dialog.open) dialog.close();
    closing = false;
    dialog.classList.remove('is-leaving');
    document.body.classList.remove('admin-more-open');
    opener.setAttribute('aria-expanded','false');
    if (mobile.matches) opener.focus({preventScroll:true});
  };
  const close = () => {
    if (!dialog.open || closing) return;
    if (reduced.matches || !mobile.matches) return closeNow();
    closing = true;
    dialog.classList.add('is-leaving');
    timer = setTimeout(closeNow, 230);
  };
  opener.addEventListener('click', () => {
    if (!mobile.matches || dialog.open) return;
    if (typeof dialog.showModal !== 'function') {
      document.querySelector('[data-admin-menu-toggle]')?.click();
      return;
    }
    dialog.showModal();
    document.body.classList.add('admin-more-open');
    opener.setAttribute('aria-expanded','true');
    closer.focus({preventScroll:true});
  });
  closer.addEventListener('click', close);
  dialog.addEventListener('cancel', e => { e.preventDefault(); close(); });
  dialog.addEventListener('click', e => { if (e.target === dialog) close(); });
  dialog.addEventListener('close', () => {
    clearTimeout(timer);closing = false;
    dialog.classList.remove('is-leaving');
    document.body.classList.remove('admin-more-open');
    opener.setAttribute('aria-expanded','false');
  });
  const badge = nav.querySelector('[data-admin-order-badge]');
  const link = nav.querySelector('[data-admin-order-link]');
  const live = nav.querySelector('[data-admin-order-live]');
  let last = null;
  let polling = false;
  let stopped = false;
  async function refreshBadge() {
    if (!mobile.matches || document.hidden || polling || stopped || !nav.dataset.orderBadgeUrl) return;
    polling = true;
    try {
      const response = await fetch(nav.dataset.orderBadgeUrl, {
        credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},
        signal:typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(8000) : undefined,
      });
      if (response.status === 401 || response.status === 403) { stopped = true; return; }
      if (!response.ok) return;
      const data = await response.json();
      if (!Number.isSafeInteger(data.count) || data.count < 0) return;
      badge.hidden = data.count === 0;
      badge.textContent = data.count > 99 ? '99+' : String(data.count);
      link.setAttribute('aria-label','Siparişler' + (data.count ? ', ' + data.count + ' yeni sipariş' : ''));
      if (last !== null && data.count > last) live.textContent = 'Yeni sipariş var. ' + data.count + ' okunmamış sipariş.';
      last = data.count;
    } catch { /* Keep the last confirmed badge on intermittent connectivity. */ }
    finally { polling = false; }
  }
  // Only visible mobile admin pages poll; no storefront traffic or push permission.
  setInterval(refreshBadge,30000);
  document.addEventListener('visibilitychange', refreshBadge);
  window.addEventListener('pageshow', refreshBadge);
  mobile.addEventListener('change', () => {
    if (!mobile.matches && dialog.open) closeNow();
    if (mobile.matches) refreshBadge();
  });
  refreshBadge();
}
