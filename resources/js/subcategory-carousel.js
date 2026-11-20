/**
 * Category-child carousel.
 * Three or fewer items stay in a stable, adaptive grid. Longer lists scroll
 * on one row with native touch momentum / keyboard support. No duplicated links
 * or offscreen carousel clones are needed.
 */
document.querySelectorAll('[data-subcategory-carousel]').forEach(section => {
  const track = section.querySelector('[data-subcategory-track]');
  const items = [...(track?.querySelectorAll('[data-subcategory-slide]') || [])];
  const toolbar = section.querySelector('[data-subcategory-toolbar]');
  const prev = section.querySelector('[data-subcategory-prev]');
  const next = section.querySelector('[data-subcategory-next]');
  const current = section.querySelector('[data-subcategory-current]');
  if (!track || items.length <= 3 || !toolbar || !prev || !next || !current) return;

  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const hoverDevice = window.matchMedia('(hover: hover)');
  const interval = Math.max(2500, Number(section.dataset.autoplayInterval) || 3000);
  let stops = [];
  let active = 0;
  let direction = 1;
  let onScreen = true;
  let hovering = false;
  let lastInteraction = 0;
  let pendingScroll = false;

  const nearest = () => {
    let closest = 0;
    let distance = Infinity;
    stops.forEach((stop, index) => {
      const offset = Math.abs(track.scrollLeft - stop.left);
      if (offset < distance) { distance = offset; closest = index; }
    });
    return closest;
  };

  const update = () => {
    if (!stops.length) return;
    active = nearest();
    current.textContent = String(stops[active].index + 1);
    prev.disabled = active === 0;
    next.disabled = active === stops.length - 1;
  };

  const measure = () => {
    const max = Math.max(0, track.scrollWidth - track.clientWidth);
    const origin = items[0].offsetLeft;
    stops = [];
    items.forEach((item, index) => {
      const left = Math.max(0, Math.min(max, item.offsetLeft - origin));
      if (!stops.length || left - stops[stops.length - 1].left > 4) {
        stops.push({ left, index });
      }
    });
    toolbar.hidden = max < 4 || stops.length < 2;
    update();
  };

  const go = (index) => {
    if (index < 0 || index >= stops.length) return;
    const destination = stops[index];
    track.scrollTo({
      left: destination.left,
      behavior: reduceMotion.matches ? 'instant' : 'smooth',
    });
    active = index;
    current.textContent = String(destination.index + 1);
    prev.disabled = index === 0;
    next.disabled = index === stops.length - 1;
  };

  const markInteraction = () => { lastInteraction = Date.now(); };
  prev.addEventListener('click', () => { markInteraction(); go(nearest() - 1); });
  next.addEventListener('click', () => { markInteraction(); go(nearest() + 1); });

  track.addEventListener('pointerdown', markInteraction, { passive: true });
  track.addEventListener('touchstart', markInteraction, { passive: true });
  track.addEventListener('wheel', markInteraction, { passive: true });
  track.addEventListener('keydown', markInteraction);

  track.addEventListener('scroll', () => {
    if (pendingScroll) return;
    pendingScroll = true;
    requestAnimationFrame(() => { pendingScroll = false; update(); });
  }, { passive: true });

  section.addEventListener('pointerenter', () => {
    if (hoverDevice.matches) hovering = true;
  });
  section.addEventListener('pointerleave', () => { hovering = false; });

  window.addEventListener('resize', measure, { passive: true });
  reduceMotion.addEventListener?.('change', measure);
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver(entries => {
      onScreen = entries[0]?.isIntersecting ?? false;
    }, { threshold: 0.15 });
    observer.observe(section);
  }
  measure();

  window.setInterval(() => {
    if (toolbar.hidden || reduceMotion.matches || !onScreen
        || document.hidden || hovering || section.querySelector(':focus-visible')
        || Date.now() - lastInteraction < 5500) return;
    const currentStop = nearest();
    if (currentStop === stops.length - 1) direction = -1;
    if (currentStop === 0) direction = 1;
    go(currentStop + direction);
  }, interval);
});
