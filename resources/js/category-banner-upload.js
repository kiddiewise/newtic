/**
 * NEWTIC category banners: large camera/PNG images are resized client-side
 * before one authenticated multipart upload. This avoids HTTP/2 body resets
 * on restrictive proxies and turns network/validation failures into feedback
 * without navigating away from the category editor.
 *
 * No user photo is sent to a third party or stored in browser persistence.
 */
const bannerForms = document.querySelectorAll('[data-category-banner-upload]');
const MAX_TOTAL = 4 * 1024 * 1024; // keep both files safely below shared proxy limits
const MAX_EACH = 2 * 1024 * 1024;
const IMAGE_TYPES = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/avif']);

function makeWebp(canvas, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob(blob => {
      if (!blob || blob.type !== 'image/webp') {
        reject(new Error('Bu tarayıcı görseli WebP formatında hazırlayamıyor.'));
      } else resolve(blob);
    }, 'image/webp', quality);
  });
}

async function imageBitmap(file) {
  if (typeof createImageBitmap === 'function') return createImageBitmap(file);
  const objectUrl = URL.createObjectURL(file);
  try {
    const image = new Image();
    image.src = objectUrl;
    await image.decode();
    return image;
  } finally {
    // In Safari the decoded Image remains usable after revocation.
    URL.revokeObjectURL(objectUrl);
  }
}

async function optimizeImage(file, kind) {
  if (!IMAGE_TYPES.has(file.type)) {
    throw new Error('Banner için yalnızca JPG, PNG, WebP ve AVIF görselleri yüklenebilir.');
  }
  if (file.size <= 950 * 1024) return file;

  const decoded = await imageBitmap(file);
  try {
    if (!decoded.width || !decoded.height) {
      throw new Error('Görsel boyutları okunamadı. Lütfen farklı bir dosya seçin.');
    }
    const bounds = kind === 'mobile_image' ? [1280, 720] : [1470, 600];
    let scale = Math.min(1, bounds[0] / decoded.width, bounds[1] / decoded.height);
    let quality = 0.86;
    let blob;
    for (let attempt = 0; attempt < 6; attempt++) {
      const canvas = document.createElement('canvas');
      canvas.width = Math.max(1, Math.round(decoded.width * scale));
      canvas.height = Math.max(1, Math.round(decoded.height * scale));
      const context = canvas.getContext('2d', { alpha: true });
      if (!context) throw new Error('Görsel işlenemedi. Lütfen başka bir tarayıcı deneyin.');
      context.drawImage(decoded, 0, 0, canvas.width, canvas.height);
      blob = await makeWebp(canvas, quality);
      canvas.width = canvas.height = 0;
      if (blob.size <= MAX_EACH) break;
      quality = Math.max(0.55, quality - 0.09);
      scale *= 0.82;
    }
    if (!blob || blob.size > MAX_EACH) {
      throw new Error('Görsel çok büyük. Lütfen daha küçük çözünürlükte yeniden deneyin.');
    }
    const base = file.name.replace(/\.[^.]+$/, '').replace(/[^a-z0-9._-]/gi, '-').slice(0, 70) || 'banner';
    return new File([blob], base + '.webp', { type: 'image/webp', lastModified: Date.now() });
  } finally {
    decoded.close?.();
  }
}

bannerForms.forEach(form => {
  const label = form.querySelector('[data-banner-upload-message]');
  const button = form.querySelector('button[type="submit"]');
  if (!label || !button) return;

  const feedback = (message, error = false) => {
    label.textContent = message;
    label.hidden = !message;
    label.classList.toggle('is-error', error);
    label.setAttribute('role', error ? 'alert' : 'status');
  };

  form.addEventListener('submit', async event => {
    if (!form.checkValidity()) return; // keep native required/date validation
    event.preventDefault();
    if (form.classList.contains('is-uploading')) return;
    const buttonText = button.textContent;
    form.classList.add('is-uploading');
    button.disabled = true;
    try {
      feedback('Banner görselleri hazırlanıyor…');
      const body = new FormData(form);
      let totalSize = 0;
      let optimizedCount = 0;
      for (const kind of ['desktop_image', 'mobile_image']) {
        const field = form.querySelector('input[name="' + kind + '"]');
        const file = field?.files?.[0];
        if (!file) continue;
        const processed = await optimizeImage(file, kind);
        if (processed !== file) optimizedCount++;
        body.set(kind, processed, processed.name);
        totalSize += processed.size;
      }
      if (totalSize > MAX_TOTAL) {
        throw new Error('İki bannerın toplam boyutu yüksek. Daha küçük görseller seçin.');
      }
      feedback(optimizedCount ? 'Görseller WebP olarak optimize edildi. Kaydediliyor…' : 'Banner kaydediliyor…');
      const response = await fetch(form.action, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok) {
        const error = payload?.errors
          ? Object.values(payload.errors).flat().join(' ')
          : payload?.message || (response.status === 413
              ? 'Dosya yükleme boyutu sunucunun sınırını aştı.'
              : 'Sunucu bannerı kaydedemedi (HTTP ' + response.status + ').');
        throw new Error(error);
      }
      if (!payload?.success || !payload.redirect) {
        throw new Error('Sunucudan beklenen kayıt onayı alınamadı. Sayfayı yenileyerek bannerı kontrol edin.');
      }
      feedback('Banner başarıyla kaydedildi. Liste güncelleniyor…');
      window.location.assign(payload.redirect);
    } catch (error) {
      feedback(error instanceof TypeError
        ? 'Sunucu bağlantısı kesildi. Aynı bannerın iki kez eklenmemesi için önce sayfayı yenileyip kontrol edin. Sorun devam ederse daha küçük görseller kullanın.'
        : error.message || 'Banner yüklenemedi. Lütfen tekrar deneyin.', true);
    } finally {
      form.classList.remove('is-uploading');
      button.disabled = false;
      button.textContent = buttonText;
    }
  });
});
