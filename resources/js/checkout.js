import { checkoutHasValidDistrict } from './province-district.js';

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
      if (input) {
        input.value = option?.dataset[field] || '';
        if (name === 'phone') input.dispatchEvent(new Event('input', { bubbles: true }));
      }
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

// The checkout gate is a UX affordance; Laravel still validates all fields
// and prices on POST, including users who bypass this client-side behavior.
const checkoutForm = document.getElementById('checkout-form');
if (checkoutForm) {
  const submitButtons = [
    ...checkoutForm.querySelectorAll('[data-checkout-submit]'),
    ...document.querySelectorAll('[data-checkout-submit][form="checkout-form"]'),
  ];
  const paymentChoices = [...checkoutForm.querySelectorAll('input[name="payment_method"]')];
  const phone = checkoutForm.elements.namedItem('phone');
  let submitting = false;
  const buttonLabelFor = () => checkoutForm.querySelector('input[name="payment_method"]:checked')?.value === 'paytr'
    ? checkoutForm.dataset.checkoutPaytrLabel : checkoutForm.dataset.checkoutBankLabel;

  const update = () => {
    if (submitting) return;

    if (phone && typeof phone.setCustomValidity === 'function') {
      const raw = phone.value.trim();
      const normalized = raw.replace(/\D/g, '');
      const valid = /^5\d{9}$/.test(normalized);
      phone.setCustomValidity(raw !== '' && !valid
        ? 'Cep telefonu numaranı 555 555 55 55 şeklinde gir.' : '');
    }

    const ready = checkoutHasValidDistrict(checkoutForm)
      && checkoutForm.checkValidity()
      && !!checkoutForm.querySelector('input[name="payment_method"]:checked');

    for (const button of submitButtons) {
      // The ready transition animates the lock's shackle opening and fading.
      const previousState = button.dataset.checkoutState;
      button.disabled = !ready;
      button.dataset.checkoutState = ready ? 'ready' : 'locked';
      const label = button.querySelector('[data-checkout-button-label]');
      if (label) label.textContent = buttonLabelFor();
      if (ready && previousState !== 'ready') {
        button.classList.remove('checkout-relock');
        // Force the transient animation to restart on revalidation.
        void button.offsetWidth;
        button.classList.add('checkout-unlock');
      } else if (!ready) {
        button.classList.remove('checkout-unlock');
        button.classList.add('checkout-relock');
      }
    }
  };

  checkoutForm.addEventListener('input', update);
  checkoutForm.addEventListener('change', update);
  addressPicker?.addEventListener('change', () => queueMicrotask(update));
  paymentChoices.forEach((radio) => radio.addEventListener('change', update));
  update();

  // On server-side errors, move directly to the first invalid field.
  const firstInvalid = checkoutForm.querySelector('[aria-invalid="true"]');
  if (firstInvalid) {
    requestAnimationFrame(() => {
      firstInvalid.scrollIntoView({ block: 'center', behavior: 'auto' });
      firstInvalid.focus({ preventScroll: true });
    });
  }

  checkoutForm.addEventListener('submit', (event) => {
    if (submitting) { event.preventDefault(); return; }
    update();
    if (!checkoutHasValidDistrict(checkoutForm) || !checkoutForm.checkValidity()) return; // Native browser validation.
    submitting = true;
    checkoutForm.setAttribute('aria-busy', 'true');
    submitButtons.forEach((button) => {
      button.disabled = true;
      button.dataset.checkoutState = 'submitting';
      button.classList.remove('checkout-unlock');
      const label = button.querySelector('[data-checkout-button-label]');
      if (label) label.textContent = 'Sipariş kaydediliyor…';
    });
  });

  // bfcache can restore old disabled states on back navigation.
  window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    submitting = false;
    checkoutForm.removeAttribute('aria-busy');
    update();
  });
}
