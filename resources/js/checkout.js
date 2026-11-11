// Checkout: saved address autofill, conditional invoice and account creation.
document.querySelectorAll('[data-checkout-invoice]').forEach((section) => {
  const fields = section.querySelector('[data-corporate-fields]');
  const inputs = [...fields.querySelectorAll('input')];
  const refresh = () => {
    const corporate = section.querySelector('input[name="invoice_type"]:checked')?.value === 'corporate';
    fields.hidden = !corporate;
    inputs.forEach((field) => { field.required = corporate; });
  };
  section.querySelectorAll('input[name="invoice_type"]').forEach((radio) => radio.addEventListener('change', refresh));
  refresh();
});

const addressPicker = document.querySelector('[data-checkout-saved-address]');
if (addressPicker) {
  addressPicker.addEventListener('change', () => {
    const option = addressPicker.selectedOptions[0];
    const form = document.getElementById('checkout-form');
    if (!form) return;
    const map = {
      address_label: 'addressLabel', full_name: 'fullName', phone: 'phone',
      city: 'city', district: 'district', street_address: 'streetAddress',
      company_title: 'companyTitle', tax_office: 'taxOffice', tax_number: 'taxNumber',
    };
    for (const [name, field] of Object.entries(map)) {
      const input = form.elements.namedItem(name);
      if (input) input.value = option?.dataset[field] || '';
    }
    const type = option?.dataset.invoiceType || 'individual';
    const radio = form.querySelector('input[name="invoice_type"][value="' + type + '"]');
    if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change', { bubbles: true })); }
    if (addressPicker.value) {
      const save = form.querySelector('input[name="save_address"]');
      if (save) save.checked = false;
    }
  });
}

document.querySelectorAll('[data-create-account]').forEach((checkbox) => {
  const fields = document.querySelector('[data-create-account-fields]');
  if (!fields) return;
  const password = fields.querySelector('input[name="account_password"]');
  const toggle = () => {
    fields.hidden = !checkbox.checked;
    if (password) password.required = checkbox.checked;
  };
  checkbox.addEventListener('change', toggle);
  toggle();
});

const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
  const submitButtons = [
    ...checkoutForm.querySelectorAll('button[type="submit"]'),
    ...document.querySelectorAll('button[form="checkout-form"]'),
  ];
  const paymentChoices = [...checkoutForm.querySelectorAll('input[name="payment_method"]')];
  const buttonLabelFor = () => checkoutForm.querySelector('input[name="payment_method"]:checked')?.value === 'paytr'
    ? 'Güvenli Test Ödemesine Geç'
    : 'Deneme Siparişini Oluştur';
  const syncPayButton = () => {
    if (!paymentChoices.length || checkoutForm.getAttribute('aria-busy') === 'true') return;
    submitButtons.forEach((button) => { button.textContent = buttonLabelFor(); });
  };
  paymentChoices.forEach((radio) => radio.addEventListener('change', syncPayButton));
  syncPayButton();
  const buttonLabels = submitButtons.map(button => button.textContent);
  let submitting = false;

  // Server-side errors are displayed beside their matching fields; bring the
  // first one into view so mobile customers don't have to hunt for it.
  const firstInvalid = checkoutForm.querySelector('[aria-invalid="true"]');
  if (firstInvalid) {
    requestAnimationFrame(() => {
      firstInvalid.scrollIntoView({ block: 'center', behavior: 'auto' });
      firstInvalid.focus({ preventScroll: true });
    });
  }

  checkoutForm.addEventListener('submit', (event) => {
    if (submitting) {
      event.preventDefault();
      return;
    }
    if (!checkoutForm.checkValidity()) return;
    submitting = true;
    checkoutForm.setAttribute('aria-busy', 'true');
    submitButtons.forEach(button => {
      button.disabled = true;
      button.textContent = 'Sipariş kaydediliyor…';
    });
  });

  // Browsers can restore disabled buttons from the back/forward cache.
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    submitting = false;
    checkoutForm.removeAttribute('aria-busy');
    submitButtons.forEach((button, index) => {
      button.disabled = false;
      button.textContent = buttonLabels[index];
    });
  });
}
