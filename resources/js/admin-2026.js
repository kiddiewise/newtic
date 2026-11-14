// NEWTIC Admin 2026: keyboard-accessible mobile navigation and product preview.
const adminToggle = document.querySelector('[data-admin-menu-toggle]');
const adminSidebar = document.querySelector('[data-admin-sidebar]');
const adminBackdrop = document.querySelector('[data-admin-sidebar-backdrop]');
if (adminToggle && adminSidebar && adminBackdrop) {
  let lastFocused = null;
  const open = () => {
    lastFocused = document.activeElement;
    adminBackdrop.hidden = false;
    document.body.classList.add('admin-sidebar-open');
    adminToggle.setAttribute('aria-expanded', 'true');
    adminToggle.setAttribute('aria-label', 'Menüyü kapat');
    adminSidebar.querySelector('a')?.focus();
  };
  const close = () => {
    document.body.classList.remove('admin-sidebar-open');
    adminToggle.setAttribute('aria-expanded', 'false');
    adminToggle.setAttribute('aria-label', 'Menüyü aç');
    adminBackdrop.hidden = true;
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
      const links = [...adminSidebar.querySelectorAll('a:not([hidden])')];
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
