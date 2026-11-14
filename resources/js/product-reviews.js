// Graceful enhancement only: browser radio inputs still work without JS.
// A short label and a tiny star-pop make the selection clear on mobile.
document.querySelectorAll('[data-review-form]').forEach((form) => {
  const choices = [...form.querySelectorAll('input[name="rating"]')];
  const caption = form.querySelector('[data-review-caption]');
  const messages = {
    1: '1 yıldız · Beklentimi karşılamadı',
    2: '2 yıldız · Geliştirilebilir',
    3: '3 yıldız · İyi',
    4: '4 yıldız · Çok iyi',
    5: '5 yıldız · Harika',
  };
  const show = () => {
    const chosen = choices.find((input) => input.checked);
    if (caption) caption.textContent = chosen
      ? messages[Number(chosen.value)]
      : 'Yıldızlara dokunarak puan verin';
    if (caption) caption.dataset.selected = chosen ? 'true' : 'false';
  };
  choices.forEach((input) => input.addEventListener('change', show));
  show();
});

// Progressive enhancement: both product sections remain visible without JS.
const productInfo = document.querySelector('[data-product-info]');
if (productInfo) {
  const tabs = [...productInfo.querySelectorAll('[data-product-tab]')];
  const panels = [...productInfo.querySelectorAll('[data-product-panel]')];
  const isReviewHash = () => /^(#urun-yorumlari|#yorum-yaz)$/.test(window.location.hash);
  const activate = (name, focus = false) => {
    tabs.forEach((tab) => {
      const selected = tab.dataset.productTab === name;
      tab.setAttribute('aria-selected', String(selected));
      tab.tabIndex = selected ? 0 : -1;
      if (selected && focus) tab.focus({ preventScroll: true });
    });
    panels.forEach((panel) => {
      panel.hidden = panel.dataset.productPanel !== name;
    });
  };
  activate(isReviewHash() ? 'reviews' : 'description');
  tabs.forEach((tab, index) => {
    tab.addEventListener('click', () => {
      const name = tab.dataset.productTab;
      activate(name, true);
      const path = window.location.pathname + window.location.search;
      history.replaceState(null, '', name === 'reviews' ? '#urun-yorumlari' : path);
    });
    tab.addEventListener('keydown', (event) => {
      const direction = event.key === 'ArrowRight' ? 1 : event.key === 'ArrowLeft' ? -1 : 0;
      if (!direction && event.key !== 'Home' && event.key !== 'End') return;
      event.preventDefault();
      const target = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1
        : (index + direction + tabs.length) % tabs.length;
      tabs[target].click();
    });
  });
  document.addEventListener('click', (event) => {
    const link = event.target.closest('a[href*="#urun-yorumlari"],a[href*="#yorum-yaz"]');
    if (!link) return;
    const url = new URL(link.href, window.location.href);
    if (url.pathname === window.location.pathname && url.search === window.location.search) {
      activate('reviews');
    }
  });
  window.addEventListener('hashchange', () => {
    if (isReviewHash()) activate('reviews');
  });
  if (isReviewHash()) requestAnimationFrame(() => {
    document.querySelector(window.location.hash)?.scrollIntoView({ block: 'start' });
  });
}
