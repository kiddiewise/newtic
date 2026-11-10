// Celebrate a newly opened order receipt once per browser tab.
// No libraries, network requests or interactive layers: lightweight and non-blocking.
const orderCelebration = document.querySelector('[data-order-celebration]');

if (orderCelebration && !window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
  const orderId = orderCelebration.dataset.orderCelebration;
  const key = `newtic:order-celebrated:${orderId}`;
  let celebrated = false;

  try {
    celebrated = window.sessionStorage.getItem(key) === '1';
    if (!celebrated) window.sessionStorage.setItem(key, '1');
  } catch {
    // Storage can be blocked by privacy settings; celebration still works.
  }

  if (!celebrated) {
    const partyIcon = orderCelebration.querySelector('.checkout-success-mark');
    partyIcon?.classList.add('is-celebrating');

    window.requestAnimationFrame(() => {
      if (!partyIcon || !partyIcon.isConnected) return;

      const bounds = partyIcon.getBoundingClientRect();
      const originX = bounds.left + bounds.width / 2;
      const originY = bounds.top + bounds.height / 2;
      const overlay = document.createElement('div');
      overlay.className = 'newtic-confetti-layer';
      overlay.setAttribute('aria-hidden', 'true');

      const colors = ['#00a3a0', '#ffd166', '#f57779', '#7465d9', '#2bba91', '#ffad4e', '#67b9ec'];
      const pieces = document.createDocumentFragment();
      const count = 56;

      for (let i = 0; i < count; i += 1) {
        const confetti = document.createElement('span');
        const direction = i % 2 === 0 ? 1 : -1;
        const reach = 75 + Math.random() * Math.min(290, window.innerWidth * 0.48);
        const burstX = direction * reach;
        const fallX = burstX + (Math.random() - 0.5) * 240;
        const burstY = -(95 + Math.random() * 185);
        const fallY = Math.max(310, window.innerHeight - originY + 100) + Math.random() * 160;
        const spin = (direction * (540 + Math.random() * 960));

        confetti.className = 'newtic-confetti-piece';
        confetti.style.left = `${originX + (Math.random() - 0.5) * 32}px`;
        confetti.style.top = `${originY + (Math.random() - 0.5) * 32}px`;
        confetti.style.backgroundColor = colors[i % colors.length];
        confetti.style.borderRadius = i % 5 === 0 ? '50%' : '2px';
        confetti.style.setProperty('--burst-x', `${burstX}px`);
        confetti.style.setProperty('--burst-y', `${burstY}px`);
        confetti.style.setProperty('--fall-x', `${fallX}px`);
        confetti.style.setProperty('--fall-y', `${fallY}px`);
        confetti.style.setProperty('--half-spin', `${spin / 3}deg`);
        confetti.style.setProperty('--full-spin', `${spin}deg`);
        confetti.style.setProperty('--piece-width', `${5 + Math.random() * 6}px`);
        confetti.style.setProperty('--piece-height', `${8 + Math.random() * 8}px`);
        confetti.style.setProperty('--delay', `${Math.random() * 130}ms`);
        confetti.style.setProperty('--duration', `${2450 + Math.random() * 800}ms`);
        pieces.appendChild(confetti);
      }

      overlay.appendChild(pieces);
      document.body.appendChild(overlay);
      window.setTimeout(() => overlay.remove(), 3700);
    });
  }
}
