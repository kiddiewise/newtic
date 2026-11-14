// Category banner counter uses native horizontal scrolling and snap points.
document.querySelectorAll('[data-category-carousel]').forEach((carousel) => {
  const track = carousel.querySelector('[data-category-track]');
  const current = carousel.querySelector('[data-category-index]');
  const slides = [...track.querySelectorAll('.category-banner-slide')];
  if (!track || !current || slides.length < 2) return;

  const update = () => {
    const x = track.scrollLeft;
    let closest = 0;
    let distance = Infinity;
    slides.forEach((slide, index) => {
      const difference = Math.abs(slide.offsetLeft - track.offsetLeft - x);
      if (difference < distance) {
        distance = difference;
        closest = index;
      }
    });
    current.textContent = String(closest + 1);
  };

  track.addEventListener('scroll', update, { passive: true });
  window.addEventListener('resize', update, { passive: true });
  update();
});
