// A short-lived, same-tab handoff preserves the sliding highlight across
// server-rendered pages. Navigation itself is never delayed or intercepted.
export const NAV_MOTION_KEY = 'newtic.admin.nav-motion.v1';
export const validIndex = value => Number.isInteger(value) && value >= 0 && value <= 3;
export function ordinaryNavigation(event) {
  return !event.defaultPrevented && event.button === 0 && !event.metaKey &&
    !event.ctrlKey && !event.shiftKey && !event.altKey;
}
export function handoffIndex(raw, currentPath, now = Date.now()) {
  try {
    const value = JSON.parse(raw);
    return value && validIndex(value.index) && value.path === currentPath
      && Number.isFinite(value.at) && value.at <= now && now - value.at <= 12000
      ? value.index : -1;
  } catch { return -1; }
}
export function rememberMotion(storage, href, origin, index, now = Date.now()) {
  try {
    const target = new URL(href, origin);
    if (target.origin !== new URL(origin).origin || !validIndex(index)) return;
    storage.setItem(NAV_MOTION_KEY, JSON.stringify({index,path:target.pathname,at:now}));
  } catch { /* blocked storage must never affect navigation */ }
}
export function takeMotion(storage, path, now = Date.now()) {
  try {
    const raw = storage.getItem(NAV_MOTION_KEY);
    storage.removeItem(NAV_MOTION_KEY);
    return handoffIndex(raw,path,now);
  } catch { return -1; }
}
