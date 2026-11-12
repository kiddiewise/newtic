import { disposeInfiniteCatalog } from './infinite-catalog.js';

/*
 * Progressive enhancement for product search.
 * Header dropdown uses a small JSON suggestion endpoint. The listing page
 * uses the same Laravel search results rendered server-side as a fragment.
 * No client-owned prices, stock or HTML templates.
 */
document.querySelectorAll('[data-search-suggest-form]').forEach((form) => {
  const input = form.querySelector('input[name="q"]');
  const list = form.querySelector('[data-suggest-results]');
  if (!input || !list) return;
  let timer;
  let pending;
  const close = () => {
    list.hidden = true;
    list.replaceChildren();
    input.setAttribute('aria-expanded', 'false');
  };
  const render = (items, term) => {
    list.replaceChildren();
    if (input.value.trim() !== term || !document.contains(form)) return;
    if (!items.length) {
      const empty = document.createElement('p');
      empty.className = 'search-suggest-empty';
      empty.textContent = 'Bu aramayla eşleşen ürün bulunamadı.';
      list.appendChild(empty);
    } else {
      items.forEach((item) => {
        const row = document.createElement('a');
        row.className = 'search-suggest-item';
        row.href = item.url;
        row.setAttribute('role', 'option');
        row.setAttribute('aria-selected', 'false');

        const image = document.createElement('img');
        image.src = item.image;
        image.alt = '';
        image.loading = 'lazy';
        image.width = 46;
        image.height = 46;
        const content = document.createElement('span');
        content.className = 'search-suggest-copy';
        const name = document.createElement('strong');
        name.textContent = item.name;
        const price = document.createElement('small');
        price.textContent = item.price;
        content.append(name, price);
        row.append(image, content);
        list.appendChild(row);
      });
    }
    const full = document.createElement('a');
    full.className = 'search-suggest-all';
    const target = new URL(form.action, window.location.href);
    target.searchParams.set('q', term);
    full.href = target.href;
    full.textContent = 'Tüm arama sonuçlarını gör';
    list.appendChild(full);
    list.hidden = false;
    input.setAttribute('aria-expanded', 'true');
  };
  const lookup = async () => {
    pending?.abort();
    const term = input.value.trim();
    if (!term) { close(); return; }
    const controller = new AbortController();
    pending = controller;
    try {
      const target = new URL(form.dataset.suggestUrl, window.location.href);
      target.searchParams.set('q', term);
      const response = await fetch(target, {
        signal: controller.signal,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
      });
      if (!response.ok) throw new Error('Search suggestions unavailable');
      const payload = await response.json();
      if (!controller.signal.aborted) render(payload.items || [], term);
    } catch (error) {
      if (error.name !== 'AbortError') close(); // Enter still submits normally.
    }
  };
  input.addEventListener('input', () => {
    clearTimeout(timer);
    pending?.abort();
    if (!input.value.trim()) { close(); return; }
    timer = setTimeout(lookup, 190);
  });
  input.addEventListener('focus', () => {
    if (input.value.trim() && list.hidden) {
      clearTimeout(timer);
      timer = setTimeout(lookup, 120);
    }
  });
  input.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') close();
    if (event.key === 'ArrowDown' && !list.hidden) {
      const first = list.querySelector('a');
      if (first) { event.preventDefault(); first.focus(); }
    }
  });
  list.addEventListener('keydown', (event) => {
    const links = [...list.querySelectorAll('a')];
    const index = links.indexOf(document.activeElement);
    if (event.key === 'Escape') {
      event.preventDefault(); close(); input.focus();
    } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const next = event.key === 'ArrowDown' ? index + 1 : index - 1;
      (links[next] || (next < 0 ? input : links[links.length - 1]))?.focus();
    }
  });
  document.addEventListener('pointerdown', (event) => {
    if (!form.contains(event.target)) close();
  });
  form.addEventListener('submit', () => { pending?.abort(); clearTimeout(timer); });
});

const catalogForm = document.querySelector('[data-live-catalog-form]');
if (catalogForm) {
  const input = catalogForm.querySelector('input[name="q"]');
  const results = document.querySelector('[data-live-search-results]');
  const heading = document.querySelector('[data-search-heading]');
  const status = document.querySelector('[data-search-status]');
  if (input && results && heading && status) {
    let timer;
    let active;
    let requestNo = 0;
    const search = async (term, writeHistory = true) => {
      active?.abort();
      const thisRequest = ++requestNo;
      const controller = new AbortController();
      active = controller;
      const target = new URL(catalogForm.action, window.location.href);
      target.searchParams.delete('page');
      if (term) target.searchParams.set('q', term);
      else target.searchParams.delete('q');
      results.setAttribute('aria-busy', 'true');
      status.textContent = 'Ürünler aranıyor…';
      try {
        const response = await fetch(target, {
          signal: controller.signal,
          credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) throw new Error('Live catalog unavailable');
        const data = await response.json();
        if (controller.signal.aborted || thisRequest !== requestNo) return;
        disposeInfiniteCatalog(results);
        results.innerHTML = data.html;
        document.dispatchEvent(new CustomEvent('newtic:catalog-results-replaced', {
          detail: { holder: results },
        }));
        heading.textContent = data.heading;
        status.textContent = data.count === 0 ? 'Ürün bulunamadı.'
          : data.count + ' ürün bulundu.';
        if (writeHistory) history.replaceState(null, '', target.pathname + target.search);
      } catch (error) {
        if (error.name !== 'AbortError' && thisRequest === requestNo) {
          status.textContent = 'Anlık arama kullanılamıyor. Ara düğmesiyle tekrar deneyebilirsin.';
        }
      } finally {
        if (thisRequest === requestNo) results.setAttribute('aria-busy', 'false');
      }
    };
    input.addEventListener('input', () => {
      clearTimeout(timer);
      active?.abort();
      const term = input.value.trim();
      timer = setTimeout(() => search(term), term ? 220 : 70);
    });
    catalogForm.addEventListener('submit', (event) => {
      event.preventDefault();
      clearTimeout(timer);
      search(input.value.trim());
    });
    window.addEventListener('popstate', () => {
      const term = new URLSearchParams(location.search).get('q') || '';
      input.value = term;
      clearTimeout(timer);
      search(term, false);
    });
  }
}
