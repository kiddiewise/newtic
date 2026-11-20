/**
 * Contextual mobile category ribbon.
 * Downward page scroll shows it below the existing sticky logo header;
 * upward page scroll hides it. Horizontal chip swipes do not toggle it.
 */
document.querySelectorAll('[data-sticky-sibling-nav]').forEach(nav => {
  const header = document.querySelector('.mobile-site-header');
  const originalSubnav = document.querySelector('.mobile-subnav');
  const track = nav.querySelector('[data-sticky-sibling-track]');
  const active = nav.querySelector('[data-sticky-sibling-current]');
  if (!header || !originalSubnav || !track) return;

  let lastY = Math.max(0, window.scrollY);
  let direction = 0;
  let distance = 0;
  let scheduled = false;
  let visible = false;

  function setVisible(show) {
    if (visible === show) return;
    visible = show;
    nav.classList.toggle('is-visible', show);
    nav.setAttribute('aria-hidden', String(!show));
    nav.inert = !show;
  }

  function position() {
    // Header may shrink on some themes; use its actual visible bottom edge.
    const top = Math.max(0, header.getBoundingClientRect().bottom);
    nav.style.setProperty('--sticky-sibling-top', top + 'px');
    return top;
  }

  function handleScroll() {
    scheduled = false;
    const top = position();
    const y = Math.max(0, window.scrollY);
    const delta = y - lastY;
    lastY = y;

    if (window.innerWidth > 760 || document.body.classList.contains('mega-menu-open')
        || y < 85 || originalSubnav.getBoundingClientRect().bottom > top + 2) {
      setVisible(false);
      distance = 0;
      direction = 0;
      return;
    }
    if (Math.abs(delta) < 2) return;

    const nextDirection = delta > 0 ? 1 : -1;
    if (nextDirection !== direction) {
      direction = nextDirection;
      distance = 0;
    }
    distance += Math.abs(delta);
    // Ignore tiny oscillations caused by mobile browser chrome / scroll bounce.
    if (distance >= 12) setVisible(nextDirection > 0);
  }

  function queueScroll() {
    if (scheduled) return;
    scheduled = true;
    window.requestAnimationFrame(handleScroll);
  }

  // The active sibling starts near the middle of the horizontally swipeable
  // strip, without moving the document's vertical scroll position.
  function centerActive() {
    if (!active || window.innerWidth > 760) return;
    const desired = active.offsetLeft - track.offsetLeft
      - (track.clientWidth - active.clientWidth) / 2;
    const max = Math.max(0, track.scrollWidth - track.clientWidth);
    track.scrollLeft = Math.min(max, Math.max(0, desired));
  }

  nav.inert = true;
  position();
  centerActive();
  window.addEventListener('scroll', queueScroll, { passive:true });
  window.addEventListener('resize', () => {
    position();
    centerActive();
    queueScroll();
  }, { passive:true });
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) setVisible(false);
    lastY = Math.max(0, window.scrollY);
    direction = 0;
    distance = 0;
  });
  window.addEventListener('pageshow', () => {
    lastY = Math.max(0, window.scrollY);
    setVisible(false);
    position();
    centerActive();
  });
});
