/*
 * Progressive enhancement for 12-item Laravel paginated listings.
 * Real links are left intact unless IntersectionObserver + fetch are supported.
 * The server provides the card HTML; frontend never calculates product prices.
 */
const catalogBindings = new WeakMap();

function attachInfiniteCatalog(root) {
  if (typeof IntersectionObserver === 'undefined' || typeof fetch !== 'function') return;
  if (!root || catalogBindings.has(root)) return;
  const grid = root.querySelector('[data-infinite-product-grid]');
  const pagination = root.querySelector('[data-infinite-pagination]');
  const trigger = root.querySelector('[data-infinite-trigger]');
  const status = root.querySelector('[data-infinite-status]');
  if (!grid || !pagination || !trigger || !status) return;

  let nextUrl = root.dataset.infiniteNextUrl || '';
  if (!nextUrl) return;
  let controller = null;
  let busy = false;
  let stopped = false;
  const restoreLinks = (message) => {
    stopped = true;
    observer.disconnect();
    pagination.hidden = false;
    root.classList.remove('is-infinite-enabled');
    trigger.hidden = false;
    trigger.classList.add('has-error');
    status.textContent = message;
  };

  const observer = new IntersectionObserver((entries) => {
    if (entries.some((entry) => entry.isIntersecting)) loadNext();
  }, { rootMargin: '550px 0px 550px 0px', threshold: 0 });

  async function loadNext() {
    if (busy || stopped || !nextUrl || !root.isConnected || document.hidden) return;
    busy = true;
    root.setAttribute('aria-busy', 'true');
    status.textContent = 'Diğer ürünler yükleniyor…';
    const pending = new AbortController();
    controller = pending;
    try {
      const url = new URL(nextUrl, window.location.href);
      const response = await fetch(url, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        signal: pending.signal,
      });
      if (!response.ok) throw new Error('Next catalog page failed');
      const result = await response.json();
      if (pending.signal.aborted || !root.isConnected) return;
      const html = document.createElement('template');
      html.innerHTML = result.html || '';
      const loaded = html.content.querySelector('[data-infinite-catalog]');
      const nextGrid = loaded?.querySelector('[data-infinite-product-grid]');
      const nextPagination = loaded?.querySelector('[data-infinite-pagination]');
      if (!loaded || !nextGrid || !nextPagination) throw new Error('Invalid catalog fragment');

      const cards = [...nextGrid.querySelectorAll(':scope > article.product-card')];
      if (!cards.length) throw new Error('Empty catalog fragment');
      grid.append(...cards);
      // Move the fallback pager along with the actual loaded page.
      pagination.replaceChildren(...nextPagination.childNodes);
      nextUrl = loaded.dataset.infiniteNextUrl || '';
      root.dataset.infiniteNextUrl = nextUrl;

      if (!nextUrl) {
        stopped = true;
        observer.disconnect();
        trigger.hidden = true;
      } else {
        status.textContent = 'Diğer ürünler kaydırdıkça yüklenecek.';
      }
      // Some storefront behaviors bind to elements present when app.js loads.
      // Delegated cart handlers continue to handle newly appended cards.
      root.dispatchEvent(new CustomEvent('newtic:catalog-items-added', {
        bubbles: true,
        detail: { count: cards.length },
      }));
    } catch (error) {
      if (error.name !== 'AbortError') {
        restoreLinks('Diğer ürünler yüklenemedi. Sayfa bağlantılarından devam edebilirsin.');
      }
    } finally {
      if (controller === pending) controller = null;
      busy = false;
      if (root.isConnected) root.setAttribute('aria-busy', 'false');
    }
  }

  const destroy = () => {
    stopped = true;
    controller?.abort();
    observer.disconnect();
    catalogBindings.delete(root);
  };
  catalogBindings.set(root, destroy);
  pagination.hidden = true;
  root.classList.add('is-infinite-enabled');
  trigger.hidden = false;
  status.textContent = 'Aşağı indikçe diğer ürünler yüklenir.';
  observer.observe(trigger);
}

function initializeCatalogs(scope = document) {
  scope.querySelectorAll('[data-infinite-catalog]').forEach(attachInfiniteCatalog);
}

initializeCatalogs();
document.addEventListener('newtic:catalog-results-replaced', (event) => {
  const holder = event.detail?.holder;
  if (holder) initializeCatalogs(holder);
});

document.addEventListener('visibilitychange', () => {
  // When a hidden tab becomes visible, reconnect if the sentinel is nearby.
  if (!document.hidden) {
    document.querySelectorAll('[data-infinite-catalog]').forEach((root) => {
      if (!catalogBindings.has(root)) attachInfiniteCatalog(root);
    });
  }
});

// Replacing the AJAX search result intentionally disconnects the old node and
// AbortController; a stale request must never append to a new query's grid.
export function disposeInfiniteCatalog(scope) {
  scope?.querySelectorAll('[data-infinite-catalog]').forEach((root) => {
    catalogBindings.get(root)?.();
  });
}
