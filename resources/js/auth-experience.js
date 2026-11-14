document.querySelectorAll('[data-auth-slideshow]').forEach((root) => {
  const slides = [...root.querySelectorAll('[data-auth-slide]')];
  const dots = [...root.parentElement.querySelectorAll('[data-auth-slide-dot]')];
  if (slides.length < 2) return;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  let current = 0;
  let timer = null;

  function show(next) {
    current = (next + slides.length) % slides.length;
    slides.forEach((slide, index) => {
      const selected = index === current;
      slide.classList.toggle('is-active', selected);
      slide.setAttribute('aria-hidden', String(!selected));
    });
    dots.forEach((dot, index) => dot.setAttribute('aria-current', String(index === current)));
  }

  function stop() {
    if (timer !== null) window.clearInterval(timer);
    timer = null;
  }
  function start() {
    stop();
    if (reduced.matches || document.hidden || root.matches(':hover') || root.matches(':focus-within')) return;
    timer = window.setInterval(() => show(current + 1), Math.max(4000, Number(root.dataset.slideDelay) || 6200));
  }
  dots.forEach((dot, index) => dot.addEventListener('click', () => {
    show(index);
    start();
  }));
  root.addEventListener('mouseenter', stop);
  root.addEventListener('mouseleave', start);
  root.addEventListener('focusin', stop);
  root.addEventListener('focusout', start);
  document.addEventListener('visibilitychange', start);
  reduced.addEventListener('change', start);
  show(0);
  start();
});