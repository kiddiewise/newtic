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
  checkoutForm.addEventListener('submit', () => {
    if (!checkoutForm.checkValidity()) return;
    checkoutForm.querySelectorAll('button[type="submit"]').forEach((button) => {
      button.disabled = true;
      button.textContent = 'Sipariş oluşturuluyor…';
    });
    document.querySelectorAll('button[form="checkout-form"]').forEach((button) => {
      button.disabled = true;
      button.textContent = 'Kaydediliyor…';
    });
  });
}
