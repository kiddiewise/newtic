// Native overflow carousel: arrow navigation, keyboard scrolling, touch swipe.
document.querySelectorAll('[data-showcase-carousel]').forEach((carousel) => {
  const track = carousel.querySelector('[data-carousel-track]');
  const prev = carousel.querySelector('[data-carousel-prev]');
  const next = carousel.querySelector('[data-carousel-next]');
  if (!track || !prev || !next) return;
  const update = () => {
    const overflow = track.scrollWidth > track.clientWidth + 2;
    prev.hidden = next.hidden = !overflow;
    prev.disabled = track.scrollLeft <= 2;
    next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
  };
  const move = (direction) => {
    const card = track.querySelector('.product-card');
    const gap = parseFloat(getComputedStyle(track).columnGap) || 0;
    const distance = card ? card.getBoundingClientRect().width + gap : track.clientWidth;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    track.scrollBy({left: direction * distance, behavior: reduced ? 'instant' : 'smooth'});
  };
  prev.addEventListener('click', () => move(-1));
  next.addEventListener('click', () => move(1));
  track.addEventListener('scroll', update, {passive: true});
  if ('ResizeObserver' in window) new ResizeObserver(update).observe(track);
  else window.addEventListener('resize', update, {passive: true});
  update();
});
document.querySelectorAll('[data-showcase-countdown]').forEach((countdown) => {
  const end = Number(countdown.dataset.end);
  const display = countdown.querySelector('[data-countdown-text]');
  if (!Number.isFinite(end) || !display) return;
  let interval;
  const tick = () => {
    const remaining = Math.max(0, Math.floor((end - Date.now()) / 1000));
    if (!remaining) { countdown.hidden = true; clearInterval(interval); return; }
    const days = Math.floor(remaining / 86400);
    const hours = Math.floor(remaining % 86400 / 3600);
    const minutes = Math.floor(remaining % 3600 / 60);
    const seconds = remaining % 60;
    display.textContent = days + ' gün ' + hours + ' saat ' + minutes + ' dakika ' + seconds + ' saniye';
  };
  tick();
  if (!countdown.hidden) interval = setInterval(tick, 1000);
});
const showcaseDialog = document.querySelector('#showcase-dialog');
document.querySelector('[data-showcase-open]')?.addEventListener('click', () => showcaseDialog?.showModal());
document.querySelector('[data-showcase-close]')?.addEventListener('click', () => showcaseDialog?.close());
showcaseDialog?.addEventListener('click', (event) => {
  if (event.target === showcaseDialog) showcaseDialog.close();
});
