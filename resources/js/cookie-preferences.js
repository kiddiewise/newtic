// Consent management shell. NEWTIC loads no optional analytics/ad scripts by
// default. Future integrations MUST check this state before loading scripts.
const key = 'newtic_cookie_preferences_v1';
const expiryMs = 180 * 24 * 60 * 60 * 1000;
const banner = document.querySelector('[data-cookie-banner]');
const dialog = document.querySelector('[data-cookie-dialog]');

if (banner && dialog) {
  let current = null;
  const safelyRead = () => {
    try {
      const parsed = JSON.parse(localStorage.getItem(key));
      if (!parsed || !parsed.savedAt || Date.now() - parsed.savedAt > expiryMs) return null;
      return {
        necessary: true,
        analytics: parsed.analytics === true,
        marketing: parsed.marketing === true,
        savedAt: parsed.savedAt,
      };
    } catch {
      return null;
    }
  };

  const announce = (value) => {
    window.NEWTIC_COOKIE_PREFERENCES = value;
    document.dispatchEvent(new CustomEvent('newtic:cookie-preferences', {
      detail: { ...value },
    }));
  };

  const save = (analytics, marketing) => {
    const value = { necessary: true, analytics, marketing, savedAt: Date.now() };
    try {
      localStorage.setItem(key, JSON.stringify(value));
    } catch {
      // Storage can be disabled; no optional technology will load implicitly.
    }
    current = value;
    announce(value);
    banner.hidden = true;
    if (dialog.open) dialog.close();
  };

  current = safelyRead();
  announce(current || { necessary: true, analytics: false, marketing: false });
  banner.hidden = !!current;

  banner.querySelector('[data-cookie-accept]')?.addEventListener('click', () => save(true, true));
  banner.querySelector('[data-cookie-decline]')?.addEventListener('click', () => save(false, false));

  const open = () => {
    dialog.querySelector('[data-cookie-analytics]').checked = current?.analytics === true;
    dialog.querySelector('[data-cookie-marketing]').checked = current?.marketing === true;
    if (!dialog.open) dialog.showModal();
  };

  banner.querySelector('[data-cookie-preferences]')?.addEventListener('click', open);
  document.querySelectorAll('[data-open-cookie-preferences]').forEach((button) => {
    button.addEventListener('click', open);
  });
  dialog.querySelector('[data-cookie-close]')?.addEventListener('click', () => dialog.close());
  dialog.querySelector('[data-cookie-save]')?.addEventListener('click', () => {
    save(
      dialog.querySelector('[data-cookie-analytics]').checked,
      dialog.querySelector('[data-cookie-marketing]').checked,
    );
  });
  window.addEventListener('storage', (event) => {
    if (event.key !== key) return;
    current = safelyRead();
    announce(current || { necessary: true, analytics: false, marketing: false });
    banner.hidden = !!current;
  });
}
