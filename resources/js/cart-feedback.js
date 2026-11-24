// Independent feedback lifecycle: stale timers must never hide a newer toast.
export function createDismissal(setTimer = setTimeout, cancelTimer = clearTimeout) {
  const timers = new WeakMap();
  return (node, delay = 3000) => {
    if (!node) return;
    const previous = timers.get(node);
    if (previous !== undefined) cancelTimer(previous);
    node.classList.remove('is-feedback-leaving');
    node.hidden = false;
    const start = setTimer(() => {
      node.classList.add('is-feedback-leaving');
      const end = setTimer(() => {
        node.hidden = true;
        node.classList.remove('is-feedback-leaving');
        timers.delete(node);
      }, 280);
      timers.set(node, end);
    }, delay);
    timers.set(node, start);
  };
}
export const dismissFeedback = createDismissal();

export function installCartFeedback() {
  document.querySelectorAll('[data-cart-auto-dismiss]').forEach(node => {
    dismissFeedback(node, Number(node.dataset.cartAutoDismiss) || 3000);
  });
}

export class CartRequestError extends Error {
  constructor(message, snapshot = null) {
    super(message);
    this.snapshot = snapshot;
  }
}

function usableSnapshot(data) {
  return data?.ok === true && Number.isInteger(data.count) && data.count >= 0
    && data.product_quantities !== null && typeof data.product_quantities === 'object'
    && typeof data.subtotal_label === 'string';
}

function readableError(body, fallback) {
  const first = body?.errors && Object.values(body.errors)[0];
  const message = (Array.isArray(first) ? first[0] : first);
  // Do not expose infrastructure errors or arbitrary upstream HTML.
  return typeof message === 'string' ? message : fallback;
}

// One mutation attempt only. On an ambiguous result, query the server with the
// same operation receipt. No automatic repeat of a write, even after a timeout.
export async function sendCartRequest(url, data, {statusUrl, fetcher = fetch, operation = globalThis.crypto?.randomUUID?.()} = {}) {
  const headers = {Accept:'application/json','X-Requested-With':'XMLHttpRequest'};
  if (operation) data.set('cart_operation', operation);
  try {
    const response = await fetcher(url, {method:'POST',credentials:'same-origin',headers,body:data});
    let json = null;
    try { json = await response.json(); } catch { /* proxy may respond with HTML */ }
    if (response.ok && usableSnapshot(json)) return json;
    if (response.status === 419) throw new CartRequestError('Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
    if (response.status === 422 || response.status === 409 || response.status === 429) {
      throw new CartRequestError(readableError(json, response.status === 429
        ? 'Çok fazla işlem yapıldı. Kısa bir süre sonra tekrar deneyin.'
        : 'Sepet güncellenemedi. Ürün miktarını kontrol edin.'));
    }
    if (response.status === 401 || response.status === 403) throw new CartRequestError('İşlem doğrulanamadı. Sayfayı yenileyin.');
    // Connection/server failure or malformed success: result is unknown.
  } catch (error) {
    if (error instanceof CartRequestError) throw error;
  }
  let snapshot = null;
  if (statusUrl) {
    const target = new URL(statusUrl, globalThis.location?.href ?? 'https://example.invalid/');
    if (operation) target.searchParams.set('operation', operation);
    try {
      const response = await fetcher(target.toString(), {method:'GET',credentials:'same-origin',headers,cache:'no-store',
        signal: typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(8000) : undefined});
      const json = response.ok ? await response.json() : null;
      if (usableSnapshot(json)) snapshot = json;
    } catch { /* do not repeat mutation */ }
  }
  if (operation && snapshot?.operation_confirmed === true) return snapshot;
  throw new CartRequestError(snapshot
    ? 'Bağlantı kesildi. Güncel sepetiniz kontrol edildi; tekrar eklemeden önce adedi kontrol edin.'
    : 'Bağlantı kurulamadı. Tekrar eklemeden önce sepetinizi kontrol edin.', snapshot);
}
