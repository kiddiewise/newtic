document.querySelectorAll('[data-slider]').forEach((slider) => {
  const slides = [...slider.querySelectorAll('[data-slide]')];
  const dots = [...slider.querySelectorAll('[data-slide-dot]')];
  if (slides.length < 2) return;
  let current = 0, timer = null, startX = null;
  const reduce = matchMedia('(prefers-reduced-motion: reduce)');
  const delay = Math.max(2500, Math.min(15000, Number(slider.dataset.interval) || 5500));
  function show(next) {
    current = (next + slides.length) % slides.length;
    slides.forEach((el, i) => {
      const active = i === current;
      el.classList.toggle('is-active', active);
      el.setAttribute('aria-hidden', String(!active));
      const a = el.querySelector('a'); if (a) a.tabIndex = active ? 0 : -1;
    });
    dots.forEach((el, i) => el.setAttribute('aria-current', String(i === current)));
  }
  function stop() { if (timer) clearInterval(timer); timer = null; }
  function start() {
    stop();
    if (reduce.matches || document.hidden || slider.matches(':hover') || slider.matches(':focus-within')) return;
    timer = setInterval(() => show(current + 1), delay);
  }
  slider.querySelector('[data-slide-prev]')?.addEventListener('click', () => {show(current - 1); start();});
  slider.querySelector('[data-slide-next]')?.addEventListener('click', () => {show(current + 1); start();});
  dots.forEach((dot, i) => dot.addEventListener('click', () => {show(i); start();}));
  slider.addEventListener('pointerdown', e => {startX = e.clientX; stop();}, {passive:true});
  slider.addEventListener('pointerup', e => {
    if (startX !== null && Math.abs(startX - e.clientX) > 45) show(current + (e.clientX < startX ? 1 : -1));
    startX = null; start();
  }, {passive:true});
  slider.addEventListener('pointercancel', () => {startX = null; start();});
  slider.addEventListener('mouseenter', stop);
  slider.addEventListener('mouseleave', start);
  slider.addEventListener('focusin', stop);
  slider.addEventListener('focusout', () => requestAnimationFrame(start));
  document.addEventListener('visibilitychange', start);
  reduce.addEventListener('change', start);
  show(0); start();
});

const quantityInputs = [...document.querySelectorAll('[data-order-quantity]')];
const summary = document.querySelector('[data-order-summary]');
if (summary) {
  const update = () => {
    const qty = quantityInputs.reduce((s, el) => s + Math.max(0, Number(el.value) || 0), 0);
    const total = quantityInputs.reduce((s, el) => s + Math.max(0, Number(el.value) || 0) * Number(el.dataset.price || 0), 0);
    summary.textContent = qty + ' adet · ' + new Intl.NumberFormat('tr-TR', {style:'currency', currency:'TRY'}).format(total);
  };
  quantityInputs.forEach(el => el.addEventListener('input', update));
  update();
}
