/* Resume-friendly importer: one authenticated POST per two catalog rows.
 * It runs only while this admin page is open; no queue worker is required.
 */
const runner = document.querySelector('[data-import-stage="running"]');
if (runner) {
  const form = runner.querySelector('[data-import-step-form]');
  const bar = runner.querySelector('[data-import-progress]');
  const caption = runner.querySelector('[data-import-caption]');
  const live = runner.querySelector('[data-import-live-status]');
  const counters = Object.fromEntries(
    ['processed','created','updated','skipped','failed'].map((key) => [
      key, runner.querySelector('[data-import-' + key + ']'),
    ]),
  );
  let stopped = false;
  let busy = false;

  const process = async () => {
    if (busy || stopped || document.visibilityState === 'hidden' || !form) return;
    busy = true;
    const button = form.querySelector('button[type="submit"]');
    button.disabled = true;
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
        },
      });
      if (!response.ok) throw new Error('HTTP ' + response.status);
      const data = await response.json();
      for (const [key, element] of Object.entries(counters)) {
        if (element) element.textContent = String(data[key] ?? 0);
      }
      bar.value = data.processed;
      caption.textContent = data.processed + ' / ' + data.total + ' satır (%' + data.percent + ')';
      live.textContent = 'Aktarım devam ediyor. Görsel dosyaları WordPress mağazasından güvenle kopyalanıyor.';
      if (data.status === 'completed') {
        stopped = true;
        live.textContent = 'Aktarım tamamlandı. Rapor açılıyor…';
        window.location.assign(data.url);
      }
    } catch {
      stopped = true;
      live.textContent = 'Bağlantı kesildi. Aktarım verileri korundu; devam etmek için butona basın veya sayfayı yenileyin.';
    } finally {
      busy = false;
      button.disabled = false;
    }
    if (!stopped && document.visibilityState === 'visible') {
      setTimeout(process, 50);
    }
  };

  form.addEventListener('submit', (event) => {
    // Normal form POST remains available with JavaScript disabled.
    event.preventDefault();
    stopped = false;
    process();
  });
  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible' && !stopped) process();
  });
  process();
}
