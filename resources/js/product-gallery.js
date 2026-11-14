// Product gallery gestures. A two-finger gesture never triggers a photo swipe.
export function pinchScale(initialDistance, currentDistance, limit = 4) {
  if (!Number.isFinite(initialDistance) || initialDistance <= 0 || !Number.isFinite(currentDistance)) return 1;
  return Math.min(limit, Math.max(1, currentDistance / initialDistance));
}

export function touchMidpoint(first, second) {
  return {
    x: (first.clientX + second.clientX) / 2,
    y: (first.clientY + second.clientY) / 2,
  };
}

function distance(first, second) {
  return Math.hypot(first.clientX - second.clientX, first.clientY - second.clientY);
}

export function initProductGallery(root = document) {
  const gallery = root.querySelector('[data-product-gallery]');
  if (!gallery) return;

  const main = gallery.querySelector('[data-product-main-image]');
  const stage = gallery.querySelector('[data-gallery-swipe]');
  const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
  const counter = gallery.querySelector('[data-gallery-current]');
  const liveStatus = gallery.querySelector('[data-gallery-live]');
  const previous = gallery.querySelector('[data-gallery-prev]');
  const next = gallery.querySelector('[data-gallery-next]');
  if (!main || !stage) return;

  const images = thumbs.length ? thumbs.map((element) => element.dataset.imageUrl) : [main.src];
  const initial = thumbs.findIndex((element) => element.dataset.imageUrl === main.getAttribute('src'));
  let index = initial < 0 ? 0 : initial;
  let swipeStart = null;
  let pinch = null;
  let locked = false;

  const resetZoom = () => {
    if (!pinch && !stage.classList.contains('is-pinching')) return;
    pinch = null;
    stage.classList.remove('is-pinching');
    main.style.transform = '';
    main.style.transformOrigin = '';
  };

  const show = (position) => {
    if (images.length < 2) return;
    resetZoom();
    index = (position + images.length) % images.length;
    main.src = images[index];
    if (counter) counter.textContent = String(index + 1);
    if (liveStatus) liveStatus.textContent = 'Fotoğraf ' + (index + 1) + ' / ' + images.length;
    thumbs.forEach((thumb, i) => thumb.setAttribute('aria-pressed', String(i === index)));
  };

  thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => show(i)));
  previous?.addEventListener('click', () => show(index - 1));
  next?.addEventListener('click', () => show(index + 1));

  // Touch events keep ordinary vertical scrolling working, unlike applying
  // touch-action:none to the entire image. Only the actual pinch is cancelled.
  const startPinch = (touches) => {
    const first = touches[0], second = touches[1];
    const initialDistance = distance(first, second);
    if (initialDistance < 8) return;
    const center = touchMidpoint(first, second);
    const rect = stage.getBoundingClientRect();
    pinch = { initialDistance, center };
    locked = true;
    swipeStart = null;
    stage.classList.add('is-pinching');
    main.style.transformOrigin =
      Math.round(center.x - rect.left) + 'px ' + Math.round(center.y - rect.top) + 'px';
  };

  stage.addEventListener('touchstart', (event) => {
    if (event.touches.length >= 2) {
      locked = true;
      swipeStart = null;
      if (!pinch) startPinch(event.touches);
      if (event.cancelable) event.preventDefault();
    } else if (event.touches.length === 1 && !locked) {
      const touch = event.touches[0];
      swipeStart = { x: touch.clientX, y: touch.clientY };
    }
  }, { passive: false });

  stage.addEventListener('touchmove', (event) => {
    if (event.touches.length < 2) return;
    locked = true;
    swipeStart = null;
    if (!pinch) startPinch(event.touches);
    if (!pinch) return;
    const factor = pinchScale(pinch.initialDistance, distance(event.touches[0], event.touches[1]));
    const center = touchMidpoint(event.touches[0], event.touches[1]);
    const dx = Math.round(center.x - pinch.center.x);
    const dy = Math.round(center.y - pinch.center.y);
    main.style.transform = 'translate3d(' + dx + 'px,' + dy + 'px,0) scale(' + factor.toFixed(3) + ')';
    if (event.cancelable) event.preventDefault();
  }, { passive: false });

  stage.addEventListener('touchend', (event) => {
    // Releasing either finger resets immediately; do not interpret the
    // remaining finger as the end of a one-finger photo swipe.
    if (locked) {
      if (event.touches.length < 2) resetZoom();
      if (event.touches.length === 0) locked = false;
      swipeStart = null;
      return;
    }
    if (!swipeStart || event.touches.length !== 0) return;
    const finger = event.changedTouches[0];
    if (!finger) return;
    const dx = finger.clientX - swipeStart.x;
    const dy = finger.clientY - swipeStart.y;
    swipeStart = null;
    if (Math.abs(dx) > 42 && Math.abs(dx) > Math.abs(dy) * 1.3) {
      show(index + (dx < 0 ? 1 : -1));
    }
  }, { passive: true });

  stage.addEventListener('touchcancel', () => {
    resetZoom();
    locked = false;
    swipeStart = null;
  }, { passive: true });

  // BFCache/orientation changes must not leave an image enlarged.
  root.addEventListener?.('visibilitychange', () => {
    if (root.hidden) {
      resetZoom();
      locked = false;
      swipeStart = null;
    }
  });
}
