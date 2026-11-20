// Autosaving NEWTIC product quick editor: one field per request, serialized to
// avoid stale updates. No save button; pending changes flush on blur/close.
const quickDialog = document.querySelector('[data-quick-editor]');
if (quickDialog && typeof quickDialog.showModal === 'function') {
  const form = quickDialog.querySelector('[data-quick-form]');
  const notice = quickDialog.querySelector('[data-quick-feedback]');
  const heading = quickDialog.querySelector('#quick-editor-title');
  const fullEdit = quickDialog.querySelector('[data-quick-full-edit]');
  const stockHint = quickDialog.querySelector('[data-quick-stock-hint]');
  const fields = [...form.querySelectorAll('[data-quick-field]')];
  const fieldMap = Object.fromEntries(fields.map(field => [field.name, field]));
  const formatter = new Intl.NumberFormat('tr-TR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const pending = new Map();
  let saved = {};
  let context = null;
  let timer = null;
  let busy = false;
  let closeWhenSaved = false;
  let errorPending = false;
  let previousFocus = null;

  const money = value => formatter.format(Number(value)) + ' ₺';
  const displayAmount = value => value == null || value === ''
    ? '' : Number(value).toFixed(2).replace('.', ',');

  function normalizedValue(field) {
    const raw = fieldMap[field].value.trim();
    return field === 'regular_price' || field === 'sale_price'
      ? raw.replace(',', '.') : raw;
  }

  function showNotice(message, kind = '') {
    notice.textContent = message;
    notice.classList.toggle('is-success', kind === 'success');
    notice.classList.toggle('is-error', kind === 'error');
    notice.setAttribute('role', kind === 'error' ? 'alert' : 'status');
  }

  function resultValue(product, field) {
    switch (field) {
      case 'name': return product.name;
      case 'primary_category_id': return String(product.primary_category_id);
      case 'regular_price': return String(product.regular_price);
      case 'sale_price': return product.sale_price == null ? '' : String(product.sale_price);
      case 'stock_quantity': return String(product.stock_quantity);
      default: return '';
    }
  }

  function formatInput(field, value) {
    return field === 'regular_price' || field === 'sale_price' ? displayAmount(value) : value;
  }

  function applySavedProduct(product, editedField, submitted) {
    const row = context?.row;
    const trigger = context?.trigger;

    if (row) {
      const name = row.querySelector('[data-quick-row-name]');
      if (name) name.textContent = product.name;
      const category = row.querySelector('[data-quick-row-category]');
      if (category) category.textContent = product.category_name;
      const price = row.querySelector('[data-quick-row-price]');
      if (price) price.textContent = money(product.price);
      const regular = row.querySelector('[data-quick-row-regular]');
      const discount = row.querySelector('[data-quick-row-discount]');
      if (regular) {
        regular.hidden = product.discount_percent <= 0;
        regular.textContent = product.sale_price == null ? '' : money(product.regular_price);
      }
      if (discount) {
        discount.hidden = product.discount_percent <= 0;
        discount.textContent = '-%' + product.discount_percent;
      }
      const stock = row.querySelector('[data-quick-row-stock]');
      if (stock && product.tracks_stock) {
        stock.textContent = 'Stok: ' + product.stock_quantity;
        stock.classList.toggle('is-low', product.stock_quantity <= 10);
      }
    }

    if (trigger) {
      trigger.dataset.name = product.name;
      trigger.dataset.categoryId = String(product.primary_category_id);
      trigger.dataset.regularPrice = String(product.regular_price);
      trigger.dataset.salePrice = product.sale_price == null ? '' : String(product.sale_price);
      trigger.dataset.stock = String(product.stock_quantity);
      trigger.dataset.tracksStock = product.tracks_stock ? '1' : '0';
    }

    for (const field of fields) {
      const value = resultValue(product, field.name);
      saved[field.name] = value;
      if (pending.has(field.name)) continue;
      if (field.name === editedField && normalizedValue(field.name) !== submitted) continue;
      // Refresh only unchanged controls so typing during an in-flight request
      // is never replaced by the server's earlier snapshot.
      if (field.name !== editedField && document.activeElement === field
          && normalizedValue(field.name) !== value) continue;
      field.value = formatInput(field.name, value);
      field.removeAttribute('aria-invalid');
    }
  }

  function cancelTimer() {
    if (timer !== null) { clearTimeout(timer); timer = null; }
  }

  async function flush() {
    cancelTimer();
    if (busy || !context || !pending.size) {
      if (!busy && !pending.size && closeWhenSaved) quickDialog.close();
      return;
    }

    const [field, value] = pending.entries().next().value;
    pending.delete(field);
    if (value === saved[field]) {
      flush();
      return;
    }

    const activeContext = context;
    busy = true;
    showNotice('Değişiklik kaydediliyor…');
    try {
      const response = await fetch(activeContext.url, {
        method: 'PATCH',
        credentials: 'same-origin',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: JSON.stringify({ field, value }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success || !payload.product) {
        const reason = payload?.errors
          ? Object.values(payload.errors).flat().join(' ')
          : payload?.message || 'Kayıt başarısız (HTTP ' + response.status + ').';
        throw new Error(reason);
      }

      if (context === activeContext) {
        applySavedProduct(payload.product, field, value);
        errorPending = false;
        showNotice('✓ ' + payload.message, 'success');
      }
    } catch (error) {
      errorPending = true;
      fieldMap[field]?.setAttribute('aria-invalid', 'true');
      showNotice(error instanceof TypeError
        ? 'Bağlantı kurulamadı. Değişiklik kaydedilmedi veya sunucu yanıtı alınamadı. Sayfayı yenileyip kontrol edin.'
        : error.message || 'Değişiklik kaydedilemedi.', 'error');
    } finally {
      busy = false;
      if (pending.size) {
        flush();
      } else if (closeWhenSaved) {
        if (errorPending) {
          closeWhenSaved = false;
        } else {
          quickDialog.close();
        }
      }
    }
  }

  function markChange(fieldName) {
    if (!context) return;
    const value = normalizedValue(fieldName);
    const input = fieldMap[fieldName];
    input.removeAttribute('aria-invalid');
    if (value === saved[fieldName]) pending.delete(fieldName);
    else pending.set(fieldName, value);
    errorPending = false;
    if (!pending.size) {
      cancelTimer();
      if (!busy) showNotice('Tüm değişiklikler kaydedildi.', 'success');
      return;
    }
    showNotice('Değişiklik kaydedilecek…');
    cancelTimer();
    timer = setTimeout(flush, fieldName === 'primary_category_id' ? 0 : 700);
  }

  const close = () => {
    cancelTimer();
    if (busy || pending.size) {
      closeWhenSaved = true;
      showNotice('Değişiklikler kaydediliyor; ardından pencere kapanacak…');
      flush();
      return;
    }
    quickDialog.close();
  };

  quickDialog.querySelectorAll('[data-quick-close]').forEach(button =>
    button.addEventListener('click', close));
  quickDialog.addEventListener('cancel', event => {
    if (busy || pending.size) { event.preventDefault(); close(); }
  });
  quickDialog.addEventListener('click', event => {
    if (event.target === quickDialog) close();
  });
  quickDialog.addEventListener('close', () => {
    cancelTimer();
    closeWhenSaved = false;
    context = null;
    pending.clear();
    previousFocus?.focus?.();
  });

  form.addEventListener('submit', event => {
    event.preventDefault();
    flush();
  });

  fields.forEach(input => {
    input.addEventListener('input', () => markChange(input.name));
    input.addEventListener('change', () => {
      markChange(input.name);
      if (input.tagName === 'SELECT') flush();
    });
    input.addEventListener('blur', () => {
      if (pending.has(input.name)) flush();
    });
  });

  document.querySelectorAll('[data-quick-edit-open]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      if (quickDialog.open) return;
      previousFocus = trigger;
      const row = trigger.closest('[data-quick-edit-row]');
      context = { trigger, row, url: trigger.dataset.productUrl, id: trigger.dataset.productId };
      saved = {
        name: trigger.dataset.name,
        primary_category_id: trigger.dataset.categoryId,
        regular_price: trigger.dataset.regularPrice,
        sale_price: trigger.dataset.salePrice,
        stock_quantity: trigger.dataset.stock,
      };
      pending.clear();
      busy = false;
      closeWhenSaved = false;
      errorPending = false;

      heading.textContent = trigger.dataset.name;
      fullEdit.href = trigger.dataset.editUrl;
      stockHint.hidden = trigger.dataset.tracksStock === '1';
      for (const input of fields) {
        input.value = formatInput(input.name, saved[input.name]);
        input.removeAttribute('aria-invalid');
      }
      showNotice('Bir alanı değiştirin; kayıt otomatik yapılacak.');
      quickDialog.showModal();
      fieldMap.name.focus();
    });
  });
}
