/**
 * NEWTIC first-party analytics. Strict opt-in: no visitor identifier, event
 * request or optional storage is created before Analytics consent.
 * IPs, identities, exact locations, full URLs and personal fields are never
 * transmitted as telemetry. Checkout/payment/account pages are excluded.
 */
const analyticsMeta = document.querySelector('meta[name="newtic-analytics-endpoint"]');
if (analyticsMeta) {
  const endpoint = analyticsMeta.content;
  const forgetUrl = document.querySelector('meta[name="newtic-analytics-forget"]')?.content;
  const token = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const storageKey = 'newtic_analytics_session_v1';
  const page = location.pathname;
  const eligiblePage = !/^\/(admin|hesabim|giris|uye-ol|sifre-|auth|siparis|kurumsal|install)(\/|$)/i.test(page)
    && !/^\/odeme\/(paytr|basarili|basarisiz)(\/|$)/i.test(page);
  let enabled = false;
  let currentSession = null;
  let heartbeat = null;

  const preference = () => window.NEWTIC_COOKIE_PREFERENCES?.analytics === true
    && navigator.globalPrivacyControl !== true;
  const source = (() => {
    const utm = new URLSearchParams(location.search).get('utm_source');
    if (utm && /^[a-z0-9._-]{1,70}$/i.test(utm)) return utm.toLowerCase();
    try {
      const from = new URL(document.referrer);
      if (from.protocol.startsWith('http') && from.hostname !== location.hostname
          && /^[a-z0-9.-]{1,90}$/i.test(from.hostname)) return from.hostname.toLowerCase();
    } catch { /* direct visit */ }
    return null;
  })();

  function session() {
    if (!currentSession) {
      try {
        let value = sessionStorage.getItem(storageKey);
        if (!value || !/^[a-f0-9-]{36}$/i.test(value)) {
          value = crypto.randomUUID();
          sessionStorage.setItem(storageKey, value);
        }
        currentSession = value;
      } catch { return null; } // no storage = no tracking
    }
    return currentSession;
  }

  function record(event, params = {}) {
    if (!eligiblePage || !enabled || !preference()) return;
    const id = session();
    if (!id) return;
    const body = JSON.stringify({
      consent: true, session:id, event, path:page, source,
      ...params,
    });
    fetch(endpoint, {
      method:'POST', mode:'same-origin', credentials:'same-origin',
      keepalive:true, headers:{ 'Content-Type':'application/json', Accept:'application/json',
        'X-CSRF-TOKEN':token }, body,
    }).catch(() => { /* analytics must never interrupt shopping */ });
  }

  function initialPage() {
    record('page_view');
    const type = document.querySelector('meta[name="newtic-analytics-page"]')?.content;
    const productId = Number(document.querySelector('meta[name="newtic-analytics-product"]')?.content);
    const categoryId = Number(document.querySelector('meta[name="newtic-analytics-category"]')?.content);
    if (type === 'product' && productId > 0) record('view_item', {product_id:productId});
    if (type === 'category' && categoryId > 0) record('view_category', {category_id:categoryId});
    if (page === '/sepet') record('view_cart');
    if (page === '/odeme') record('begin_checkout');
    if (page === '/ara') {
      const term = new URLSearchParams(location.search).get('q');
      const count = Number(document.querySelector('meta[name="newtic-analytics-search-count"]')?.content);
      if (term) record('search', {term:term.slice(0,100),
        results_count:Number.isInteger(count) && count >= 0 ? count : null});
    }
  }

  function stopAndPurge() {
    if (heartbeat) clearInterval(heartbeat);
    heartbeat = null;
    enabled = false;
    let key = currentSession;
    try {
      key ||= sessionStorage.getItem(storageKey);
      sessionStorage.removeItem(storageKey);
    } catch { /* ignore blocked storage */ }
    currentSession = null;
    if (key && forgetUrl) {
      fetch(forgetUrl, {method:'POST', credentials:'same-origin', keepalive:true,
        headers:{'Content-Type':'application/json','X-CSRF-TOKEN':token,Accept:'application/json'},
        body:JSON.stringify({session:key})}).catch(() => {});
    }
  }

  function syncConsent() {
    if (!eligiblePage) return;
    if (!preference()) {
      if (enabled || currentSession) stopAndPurge();
      return;
    }
    if (enabled) return;
    enabled = true;
    initialPage();
    heartbeat = setInterval(() => {
      if (!document.hidden && document.hasFocus()) record('heartbeat');
    }, 45000);
  }
  window.NEWTIC_ANALYTICS = { record };
  document.addEventListener('newtic:cookie-preferences', syncConsent);
  document.addEventListener('newtic:catalog-results-replaced', e => {
    if (page !== '/ara') return;
    const term = e.detail?.term;
    const count = Number(e.detail?.count);
    if (typeof term === 'string' && term.trim()) {
      record('search', {term:term.slice(0,100),
        results_count:Number.isInteger(count) && count >= 0 ? count : null});
    }
  });
  document.addEventListener('newtic:cart-added', e => {
    const id = Number(e.detail?.productId);
    if (Number.isInteger(id) && id > 0) record('add_to_cart', {product_id:id});
  });
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && enabled) record('heartbeat');
  });
  syncConsent();
}
