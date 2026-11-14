const merchantSync = document.querySelector('[data-google-merchant-sync]');
if (merchantSync) {
  const startButton = merchantSync.querySelector('[data-sync-launch]');
  const resumeButton = merchantSync.querySelector('[data-sync-resume]');
  const progress = merchantSync.querySelector('[data-sync-progress]');
  let inFlight = false;

  async function post(url) {
    const response = await fetch(url, {
      method: 'POST', credentials: 'same-origin', cache: 'no-store',
      headers: {
        'Accept': 'application/json',
        'X-CSRF-TOKEN': merchantSync.dataset.csrf,
        'X-Requested-With': 'XMLHttpRequest',
      },
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(json.error || 'Google aktarımı başlatılamadı (HTTP ' + response.status + ').');
    return json;
  }

  function update(data) {
    progress.replaceChildren();
    const heading = document.createElement('strong');
    heading.textContent = data.done ? 'Aktarım tamamlandı' : 'Google’a ürün gönderiliyor…';
    const detail = document.createElement('p');
    detail.textContent = 'Gönderilen: ' + (data.sent ?? 0) +
      ' · Değişmeyen: ' + (data.unchanged ?? 0) +
      ' · Kaldırılan: ' + (data.removed ?? 0) +
      ' · Hata: ' + (data.errors ?? 0);
    progress.append(heading, detail);
    if (data.message) {
      const message = document.createElement('p');
      message.textContent = data.message;
      progress.append(message);
    }
  }

  async function run(restart) {
    if (inFlight) return;
    inFlight = true;
    startButton.disabled = true;
    resumeButton.disabled = true;
    try {
      if (restart) {
        const begun = await post(merchantSync.dataset.startUrl);
        update(begun);
      }
      for (;;) {
        const state = await post(merchantSync.dataset.stepUrl);
        update(state);
        if (state.done) break;
      }
    } catch (error) {
      const p = document.createElement('p');
      p.textContent = error.message || 'Google aktarımı tamamlanamadı.';
      progress.append(p);
    } finally {
      inFlight = false;
      startButton.disabled = false;
      resumeButton.disabled = false;
    }
  }

  startButton?.addEventListener('click', () => run(true));
  resumeButton?.addEventListener('click', () => run(false));
}