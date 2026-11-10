// Bank transfer instructions on the order confirmation page.
// Customer copying IBAN/order reference never submits a payment claim.
const copyButtons = [...document.querySelectorAll('[data-copy-value]')];
if (copyButtons.length) {
  const toast = document.querySelector('[data-payment-copy-toast]');
  const live = document.querySelector('[data-payment-copy-status]');
  let dismissTimer;

  const notify = (message, success) => {
    if (live) live.textContent = message;
    if (toast) {
      toast.textContent = message;
      toast.dataset.kind = success ? 'success' : 'error';
      toast.hidden = false;
      clearTimeout(dismissTimer);
      dismissTimer = setTimeout(() => { toast.hidden = true; }, 2600);
    }
  };

  const copyFallback = (value) => {
    const field = document.createElement('textarea');
    field.value = value;
    field.readOnly = true;
    field.style.position = 'fixed';
    field.style.opacity = '0';
    document.body.appendChild(field);
    field.select();
    const copied = document.execCommand('copy');
    field.remove();
    if (!copied) throw new Error('Copy not supported');
  };

  copyButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const value = button.dataset.copyValue;
      if (!value) return;
      try {
        if (navigator.clipboard?.writeText) {
          await navigator.clipboard.writeText(value);
        } else {
          copyFallback(value);
        }
        notify((button.dataset.copyLabel || 'Bilgi') + ' kopyalandı', true);
      } catch {
        notify('Kopyalanamadı. Lütfen metni seçip kopyalayın.', false);
      }
    });
  });
}
