import { validIndex, ordinaryNavigation, rememberMotion, takeMotion } from './admin-nav-motion.js';

const nav = document.querySelector('[data-admin-mobile-nav]');
const dialog = document.querySelector('[data-admin-more-dialog]');
if (nav && dialog) {
  const mobile = matchMedia('(max-width: 900px)');
  const reduced = matchMedia('(prefers-reduced-motion: reduce)');
  const opener = nav.querySelector('[data-admin-more-open]');
  const closer = dialog.querySelector('[data-admin-more-close]');
  const slider = nav.querySelector('[data-admin-nav-slider]');
  const items = [...nav.querySelectorAll('[data-admin-nav-index]')];
  const routeIndex = Number(nav.dataset.activeIndex);
  let visualIndex = routeIndex;
  let frame = 0;
  const select = (index, animate = true) => {
    cancelAnimationFrame(frame);
    const show = validIndex(index);
    nav.classList.toggle('is-motion-static', !animate || !validIndex(visualIndex));
    slider.hidden = !show;
    if (show) nav.style.setProperty('--admin-nav-index', String(index));
    items.forEach(item => item.classList.toggle('is-motion-selected', show && Number(item.dataset.adminNavIndex) === index));
    visualIndex = index;
    if (!animate) frame = requestAnimationFrame(() => nav.classList.remove('is-motion-static'));
  };
  let storage;
  try { storage = window.sessionStorage; } catch { /* privacy mode */ }
  const previous = takeMotion(storage, location.pathname);
  nav.classList.add('is-motion-ready');
  if (mobile.matches && !reduced.matches && validIndex(previous) && validIndex(routeIndex) && previous !== routeIndex) {
    select(previous, false);
    frame = requestAnimationFrame(() => {
      nav.classList.remove('is-motion-static');
      frame = requestAnimationFrame(() => select(routeIndex));
    });
  } else select(routeIndex, false);
  // Normal links remain ordinary links (including Ctrl-click, back, new tabs).
  const remember = (event, anchor, index) => {
    if (!mobile.matches || !ordinaryNavigation(event) || anchor.hasAttribute('download')
      || (anchor.target && anchor.target !== '_self')) return;
    rememberMotion(storage,anchor.href,location.origin,index);
  };
  nav.querySelectorAll('a[data-admin-nav-index]').forEach(anchor => {
    anchor.addEventListener('click', event => remember(event,anchor,visualIndex));
  });
  dialog.querySelectorAll('a[href]').forEach(anchor => {
    anchor.addEventListener('click', event => remember(event,anchor,3));
  });
  let closing = false;
  let timer = null;
  const closeNow = () => {
    clearTimeout(timer);
    if (dialog.open) dialog.close();
    closing = false;
    dialog.classList.remove('is-leaving');
    document.body.classList.remove('admin-more-open');
    opener.setAttribute('aria-expanded','false');
    select(routeIndex);
    if (mobile.matches) opener.focus({preventScroll:true});
  };
  const close = () => {
    if (!dialog.open || closing) return;
    if (reduced.matches || !mobile.matches) return closeNow();
    closing = true;
    dialog.classList.add('is-leaving');
    timer = setTimeout(closeNow, 260);
  };
  opener.addEventListener('click', () => {
    if (!mobile.matches || dialog.open) return;
    if (typeof dialog.showModal !== 'function') {
      document.querySelector('[data-admin-menu-toggle]')?.click();
      return;
    }
    select(3);
    dialog.showModal();
    document.body.classList.add('admin-more-open');
    opener.setAttribute('aria-expanded','true');
    closer.focus({preventScroll:true});
  });
  closer.addEventListener('click', close);
  dialog.addEventListener('cancel', e => { e.preventDefault(); close(); });
  dialog.addEventListener('click', e => { if (e.target === dialog) close(); });
  dialog.addEventListener('close', () => {
    clearTimeout(timer);closing = false;
    dialog.classList.remove('is-leaving');
    document.body.classList.remove('admin-more-open');
    opener.setAttribute('aria-expanded','false');
    if (visualIndex !== routeIndex) select(routeIndex);
  });
  const badge = nav.querySelector('[data-admin-order-badge]');
  const link = nav.querySelector('[data-admin-order-link]');
  const live = nav.querySelector('[data-admin-order-live]');
  const soundButton = document.querySelector('[data-admin-order-sound]');
  const soundLabel = document.querySelector('[data-admin-sound-label]');
  const toast = document.querySelector('[data-admin-order-toast]');
  const toastText = document.querySelector('[data-admin-order-toast-text]');
  const soundKey = 'newtic.admin.order-sound.' + nav.dataset.orderUser;
  const playedKey = soundKey + '.latest';
  const getPref = key => { try { return localStorage.getItem(key); } catch { return null; } };
  const setPref = (key,value) => { try { localStorage.setItem(key,value); } catch {} };
  let soundEnabled = getPref(soundKey) === '1';
  let audioContext = null;
  let highWater = Number(nav.dataset.orderLatestId) || 0;
  let last = Number(badge.textContent === '99+' ? 100 : badge.textContent) || 0;
  let toastTimer;
  const updateSound = () => {
    if (!soundButton) return;
    soundButton.setAttribute('aria-pressed', String(soundEnabled));
    soundButton.setAttribute('aria-label', soundEnabled ? 'Sipariş sesini kapat' : 'Sipariş sesini etkinleştir');
    soundButton.title = soundEnabled ? 'Sipariş sesi açık' : 'Sipariş sesi kapalı';
    if (soundLabel) soundLabel.textContent = soundEnabled ? 'Ses açık' : 'Ses kapalı';
  };
  async function unlockAudio() {
    const Audio = window.AudioContext || window.webkitAudioContext;
    if (!Audio) return false;
    try {
      audioContext ||= new Audio();
      if (audioContext.state !== 'running') await audioContext.resume();
      return audioContext.state === 'running';
    } catch { return false; }
  }
  function playChime() {
    if (!audioContext || audioContext.state !== 'running') return false;
    const t = audioContext.currentTime;
    [660,880].forEach((frequency,i) => {
      const note = audioContext.createOscillator(), gain = audioContext.createGain();
      const start = t + i * .15;
      note.type = 'sine'; note.frequency.value = frequency;
      gain.gain.setValueAtTime(.001,start);
      gain.gain.exponentialRampToValueAtTime(.12,start+.02);
      gain.gain.exponentialRampToValueAtTime(.001,start+.22);
      note.connect(gain); gain.connect(audioContext.destination);
      note.start(start); note.stop(start+.23);
    });
    return true;
  }
  soundButton?.addEventListener('click', async () => {
    if (soundEnabled) { soundEnabled = false; }
    else { soundEnabled = await unlockAudio(); if (soundEnabled) playChime(); }
    setPref(soundKey,soundEnabled ? '1' : '0');
    updateSound();
  });
  document.addEventListener('pointerdown', event => {
    if (soundEnabled && !soundButton?.contains(event.target)) unlockAudio();
  }, {passive:true});
  document.addEventListener('keydown', () => { if (soundEnabled) unlockAudio(); });
  window.addEventListener('storage', event => {
    if (event.key === soundKey) { soundEnabled = event.newValue === '1'; updateSound(); }
  });
  updateSound();
  let polling = false;
  let stopped = false;
  async function refreshBadge() {
    if (document.hidden || polling || stopped || !nav.dataset.orderBadgeUrl) return;
    polling = true;
    try {
      const response = await fetch(nav.dataset.orderBadgeUrl, {
        credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'},
        signal:typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function' ? AbortSignal.timeout(8000) : undefined,
      });
      if (response.status === 401 || response.status === 403) { stopped = true; return; }
      if (!response.ok) return;
      const data = await response.json();
      if (!Number.isSafeInteger(data.count) || data.count < 0) return;
      badge.hidden = data.count === 0;
      badge.textContent = data.count > 99 ? '99+' : String(data.count);
      link.setAttribute('aria-label','Siparişler' + (data.count ? ', ' + data.count + ' yeni sipariş' : ''));
      const newest = Number.isSafeInteger(data.latest_id) ? data.latest_id : 0;
      const arrived = newest > highWater;
      if (arrived || data.count > last) {
        live.textContent = 'Yeni sipariş var. ' + data.count + ' okunmamış sipariş.';
        if (!reduced.matches && typeof badge.animate === 'function') badge.animate(
          [{transform:'scale(.8)'},{transform:'scale(1.18)'},{transform:'scale(1)'}],
          {duration:300,easing:'ease-out'}
        );
      }
      if (arrived) {
        highWater = newest;
        if (toast && toastText) {
          toastText.textContent = 'Yeni sipariş var!';
          toast.hidden = false;
          clearTimeout(toastTimer);
          toastTimer = setTimeout(() => { toast.hidden = true; }, 10000);
        }
        if (soundEnabled && newest > Number(getPref(playedKey) || 0) && playChime()) {
          setPref(playedKey,String(newest));
        }
      }
      last = data.count;
    } catch { /* Keep the last confirmed badge on intermittent connectivity. */ }
    finally { polling = false; }
  }
  // Only visible authenticated admin pages poll; no storefront traffic.
  setInterval(refreshBadge,15000);
  document.addEventListener('visibilitychange', refreshBadge);
  window.addEventListener('pageshow', event => {
    if (event.persisted) select(dialog.open ? 3 : routeIndex, false);
    refreshBadge();
  });
  mobile.addEventListener('change', () => {
    if (!mobile.matches && dialog.open) closeNow();
    if (mobile.matches) { select(dialog.open ? 3 : routeIndex, false); refreshBadge(); }
  });
  refreshBadge();
}
