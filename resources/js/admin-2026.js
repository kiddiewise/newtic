// NEWTIC Admin 2026: keyboard-accessible mobile navigation and product preview.
const adminToggle = document.querySelector('[data-admin-menu-toggle]');
const adminSidebar = document.querySelector('[data-admin-sidebar]');
const adminBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');
if (adminToggle && adminSidebar && adminBackdrop) {
  let lastFocused = null;
  const mobileScreen = window.matchMedia('(max-width: 900px)');
  const syncSidebarAccessibility = () => {
    adminSidebar.inert = mobileScreen.matches && !document.body.classList.contains('admin-sidebar-open');
  };
  syncSidebarAccessibility();
  const open = () => {
    lastFocused = document.activeElement;
    adminBackdrop.hidden = false;
    document.body.classList.add('admin-sidebar-open');
    syncSidebarAccessibility();
    adminToggle.setAttribute('aria-expanded', 'true');
    adminToggle.setAttribute('aria-label', 'Menüyü kapat');
    adminSidebar.querySelector('a')?.focus();
  };
  const close = () => {
    document.body.classList.remove('admin-sidebar-open');
    adminToggle.setAttribute('aria-expanded', 'false');
    adminToggle.setAttribute('aria-label', 'Menüyü aç');
    adminBackdrop.hidden = true;
    syncSidebarAccessibility();
    lastFocused?.focus?.();
  };
  adminToggle.addEventListener('click', () => {
    document.body.classList.contains('admin-sidebar-open') ? close() : open();
  });
  adminBackdrop.addEventListener('click', close);
  adminSidebar.querySelectorAll('a').forEach(link => link.addEventListener('click', close));
  document.addEventListener('keydown', event => {
    if (!document.body.classList.contains('admin-sidebar-open')) return;
    if (event.key === 'Escape') {
      event.preventDefault();
      close();
    }
    if (event.key === 'Tab') {
      const links = [...adminSidebar.querySelectorAll('a, button, summary')].filter(el => el.getClientRects().length > 0 && !el.disabled);
      if (!links.length) return;
      const first = links[0], last = links[links.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    }
  });
  mobileScreen.addEventListener('change', syncSidebarAccessibility);
  window.matchMedia('(min-width: 901px)').addEventListener('change', event => {
    if (event.matches && document.body.classList.contains('admin-sidebar-open')) close();
  });
}

const photoInput = document.querySelector('[data-admin-image-input]');
const photoHero = document.querySelector('[data-admin-product-hero]');
const photoThumbs = document.querySelector('[data-admin-new-thumbnails]');
if (photoInput && photoHero && photoThumbs) {
  let previews = [];
  photoInput.addEventListener('change', () => {
    previews.forEach(URL.revokeObjectURL);
    previews = [];
    photoThumbs.replaceChildren();
    const pictures = [...(photoInput.files || [])].slice(0, 8);
    for (const file of pictures) {
      if (!file.type.startsWith('image/')) continue;
      const url = URL.createObjectURL(file);
      previews.push(url);
      const image = document.createElement('img');
      image.src = url;
      image.alt = 'Seçilen yeni ürün görseli';
      image.width = 58;
      image.height = 58;
      photoThumbs.appendChild(image);
    }
    photoThumbs.hidden = previews.length === 0;
    if (previews.length) photoHero.src = previews[0];
  });
  window.addEventListener('pagehide', () => previews.forEach(URL.revokeObjectURL), {once:true});
}

document.querySelectorAll('[data-admin-saving]').forEach(button => {
  button.closest('form')?.addEventListener('submit', () => {
    if (!button.closest('form').checkValidity()) return;
    button.disabled = true;
    button.textContent = 'Kaydediliyor…';
  });
});

const adminGlobalSearch = document.querySelector('.admin-global-search input');
if (adminGlobalSearch) {
  document.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') {
      event.preventDefault();
      adminGlobalSearch.focus();
      adminGlobalSearch.select();
    }
  });
}

// Category transfer/delete dialog: one accessible instance for the entire tree.
const categoryDialog = document.querySelector('[data-category-transfer-dialog]');
if (categoryDialog) {
  const heading = categoryDialog.querySelector('#category-transfer-title');
  const description = categoryDialog.querySelector('[data-category-transfer-description]');
  const childWarning = categoryDialog.querySelector('[data-category-transfer-children]');
  const transferForm = categoryDialog.querySelector('[data-category-transfer-form]');
  const emptyDeleteForm = categoryDialog.querySelector('[data-category-empty-delete]');
  const target = transferForm.querySelector('select[name="target_category_id"]');
  const moveButton = categoryDialog.querySelector('[data-category-transfer-only]');
  const moveAndDeleteButton = categoryDialog.querySelector('[data-category-transfer-and-delete]');
  let previousFocus = null;

  const close = () => categoryDialog.close();
  categoryDialog.querySelectorAll('[data-category-transfer-close]').forEach(button =>
    button.addEventListener('click', close)
  );
  categoryDialog.addEventListener('close', () => previousFocus?.focus?.());
  categoryDialog.addEventListener('click', event => {
    if (event.target === categoryDialog) close();
  });
  categoryDialog.addEventListener('keydown', event => {
    if (event.key === 'Escape') categoryDialog.close();
  });

  document.querySelectorAll('[data-category-delete-open]').forEach(button => {
    button.addEventListener('click', () => {
      previousFocus = button;
      const count = Number(button.dataset.productCount) || 0;
      const children = Number(button.dataset.childrenCount) || 0;
      const sourceId = button.dataset.categoryId;
      const hasProducts = count > 0;
      heading.textContent = button.dataset.categoryName;
      description.textContent = hasProducts
        ? 'Bu kategoride ' + count + ' ürün bağlantısı bulunduğu için doğrudan silinemez. Ürünleri başka bir kategoriye taşıyın.'
        : children ? 'Bu kategorinin alt kategorileri bulunduğu için doğrudan silinemez.'
          : 'Bu kategori boş. Kalıcı olarak silmek istediğinize emin misiniz?';
      childWarning.hidden = children === 0;
      childWarning.textContent = children
        ? 'Bu kategorinin ' + children + ' alt kategorisi var. Ürünleri taşıyabilirsiniz; alt kategoriler kaldırılmadan kategoriyi silemezsiniz.'
        : '';
      transferForm.hidden = !hasProducts;
      emptyDeleteForm.hidden = hasProducts || children > 0;
      transferForm.action = button.dataset.transferUrl;
      emptyDeleteForm.action = button.dataset.deleteUrl;
      target.value = '';
      [...target.options].forEach(option => {
        option.disabled = option.value !== '' && option.value === sourceId;
      });
      const available = [...target.options].some(option => option.value && !option.disabled);
      moveButton.disabled = !available;
      moveAndDeleteButton.disabled = !available || children > 0;
      categoryDialog.showModal();
      if (hasProducts && available) target.focus();
    });
  });
  transferForm.addEventListener('submit', event => {
    const action = event.submitter?.value;
    if (!target.value || target.selectedOptions[0]?.disabled
      || (action === 'move_and_delete' && moveAndDeleteButton.disabled)) {
      event.preventDefault();
      target.reportValidity();
    }
  });
}
