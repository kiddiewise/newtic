// Photo requirements are tied to each individual SKU/option. Server validation
// remains authoritative; this only adds local previews and helpful inline errors.
const productPhotoForm = document.getElementById('pdp-cart-form');
if (productPhotoForm) {
  const fields = [...document.querySelectorAll('[data-customer-photos]')];
  const quantities = [...document.querySelectorAll('[data-order-quantity]')];
  const choices = [...document.querySelectorAll('[data-product-option]')];

  const selection = (block) => {
    const id = block.dataset.productId;
    const quantity = quantities.find(field => field.dataset.productId === id);
    const option = choices.find(field => field.dataset.productId === id);
    const hasOld = option
      ? block.dataset.existingPhotoOptions.split(',').includes(option.value) && option.value !== ''
      : block.dataset.existingPlainPhotos === '1';
    const input = block.querySelector('[data-customer-photo-input]');
    return {quantity: Number(quantity?.value || 0), input, hasOld, option};
  };

  const showStatus = (block) => {
    const {quantity, input, hasOld} = selection(block);
    const count = input?.files?.length || 0;
    const label = block.querySelector('[data-customer-photo-status]');
    const invalid = quantity > 0 && count === 0 && !hasOld;
    const tooMany = count > 8;
    const tooLarge = [...(input?.files || [])].some(file => file.size > 5 * 1024 * 1024);
    label.classList.toggle('is-missing', invalid || tooMany || tooLarge);
    if (tooMany) label.textContent = 'En fazla 8 fotoğraf yükleyebilirsiniz.';
    else if (tooLarge) label.textContent = 'Her fotoğraf en fazla 5 MB olabilir.';
    else if (count) label.textContent = count + ' fotoğraf seçildi. Siparişe özel ve gizli tutulur.';
    else if (hasOld && quantity > 0) label.textContent = 'Bu ürün/seçenek için fotoğraf daha önce sepete eklendi.';
    else if (invalid) label.textContent = 'Sepete eklemek için en az bir fotoğraf seçmelisiniz.';
    else label.textContent = 'Fotoğraf seçmek için yukarıdaki alana dokunun.';
    return invalid || tooMany || tooLarge;
  };

  for (const block of fields) {
    const input = block.querySelector('[data-customer-photo-input]');
    const preview = block.querySelector('[data-photo-previews]');
    let urls = [];
    input?.addEventListener('change', () => {
      for (const url of urls) URL.revokeObjectURL(url);
      urls = [];
      preview.replaceChildren();
      for (const file of [...input.files].slice(0, 8)) {
        const url = URL.createObjectURL(file);
        urls.push(url);
        const figure = document.createElement('span');
        figure.className = 'pdp-customer-photo-preview';
        const image = document.createElement('img');
        image.src = url;
        image.alt = file.name;
        const name = document.createElement('small');
        name.textContent = file.name;
        figure.append(image, name);
        preview.append(figure);
      }
      preview.hidden = !preview.childElementCount;
      showStatus(block);
    });
    showStatus(block);
  }
  quantities.forEach(field => field.addEventListener('input', () => {
    fields.forEach(showStatus);
  }));
  choices.forEach(field => field.addEventListener('change', () => fields.forEach(showStatus)));

  productPhotoForm.addEventListener('submit', (event) => {
    for (const block of fields) {
      if (!showStatus(block)) continue;
      event.preventDefault();
      event.stopImmediatePropagation();
      const picker = document.getElementById('pdp-picker');
      if (window.matchMedia('(max-width:760px)').matches && !document.body.classList.contains('pdp-sheet-open')) {
        const open = document.querySelector('[data-sheet-open]');
        open?.click();
      }
      const input = block.querySelector('[data-customer-photo-input]');
      block.scrollIntoView({behavior:'smooth',block:'center'});
      input?.focus({preventScroll:true});
      return;
    }
  }, true);
}
