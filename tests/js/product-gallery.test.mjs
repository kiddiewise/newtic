import test from 'node:test';
import assert from 'node:assert/strict';
import { pinchScale, touchMidpoint, initProductGallery } from '../../resources/js/product-gallery.js';

function makeElement(extra = {}) {
  const listeners = new Map();
  const values = new Set();
  return {
    listeners,
    style: {},
    classList: {
      add(value) { values.add(value); },
      remove(value) { values.delete(value); },
      contains(value) { return values.has(value); },
    },
    addEventListener(name, fn) {
      const queue = listeners.get(name) ?? [];
      queue.push(fn);
      listeners.set(name, queue);
    },
    fire(name, data = {}) {
      for (const fn of listeners.get(name) ?? []) fn(data);
    },
    setAttribute(name, value) { this[name] = value; },
    getBoundingClientRect() { return { left: 0, top: 0, width: 340, height: 340 }; },
    ...extra,
  };
}

function makeGallery(count = 2) {
  const main = makeElement({ src: '/first.webp', getAttribute: () => '/first.webp' });
  const stage = makeElement();
  const counter = makeElement({ textContent: '1' });
  const live = makeElement({ textContent: '' });
  const prev = makeElement();
  const next = makeElement();
  const thumbnails = count === 2
    ? ['/first.webp', '/second.webp'].map((url) => makeElement({ dataset: { imageUrl: url } }))
    : [];
  const elements = {
    '[data-product-main-image]': main,
    '[data-gallery-swipe]': stage,
    '[data-gallery-current]': counter,
    '[data-gallery-live]': live,
    '[data-gallery-prev]': prev,
    '[data-gallery-next]': next,
  };
  const gallery = {
    querySelector: (selector) => elements[selector],
    querySelectorAll: () => thumbnails,
  };
  const root = makeElement({ querySelector: () => gallery });
  initProductGallery(root);
  return { main, stage, counter, live, prev, next, thumbnails };
}
const finger = (x, y = 150) => ({ clientX: x, clientY: y });

test('pinch scale is clamped and center is calculated correctly', () => {
  assert.equal(pinchScale(100, 200), 2);
  assert.equal(pinchScale(100, 900), 4);
  assert.equal(pinchScale(100, 30), 1);
  assert.equal(pinchScale(0, 100), 1);
  assert.deepEqual(touchMidpoint(finger(10, 20), finger(50, 60)), { x: 30, y: 40 });
});

test('desktop arrows move forward/back, wrap, and update thumbnails and counter', () => {
  const gallery = makeGallery();
  gallery.next.fire('click');
  assert.equal(gallery.main.src, '/second.webp');
  assert.equal(gallery.counter.textContent, '2');
  assert.equal(gallery.thumbnails[1]['aria-pressed'], 'true');
  gallery.next.fire('click');
  assert.equal(gallery.main.src, '/first.webp');
  gallery.prev.fire('click');
  assert.equal(gallery.main.src, '/second.webp');
  gallery.thumbnails[0].fire('click');
  assert.equal(gallery.main.src, '/first.webp');
});

test('mobile pinch zooms and releasing one finger resets without changing photo', () => {
  const gallery = makeGallery();
  let cancelled = 0;
  gallery.stage.fire('touchstart', {
    touches: [finger(100), finger(200)],
    cancelable: true, preventDefault: () => { cancelled++; },
  });
  gallery.stage.fire('touchmove', {
    touches: [finger(55), finger(245)],
    cancelable: true, preventDefault: () => { cancelled++; },
  });
  assert.match(gallery.main.style.transform, /scale\(1\.900\)/);
  assert.ok(cancelled > 0);
  gallery.stage.fire('touchend', { touches: [finger(55)], changedTouches: [finger(245)] });
  assert.equal(gallery.main.style.transform, '');
  gallery.stage.fire('touchend', { touches: [], changedTouches: [finger(55)] });
  assert.equal(gallery.main.src, '/first.webp');
  assert.equal(gallery.counter.textContent, '1');
});

test('one finger swipes images without affecting normal vertical scrolling', () => {
  const gallery = makeGallery();
  gallery.stage.fire('touchstart', { touches: [finger(200, 60)], cancelable: true });
  gallery.stage.fire('touchend', { touches: [], changedTouches: [finger(80, 65)] });
  assert.equal(gallery.main.src, '/second.webp');
  gallery.stage.fire('touchstart', { touches: [finger(120, 55)], cancelable: true });
  gallery.stage.fire('touchend', { touches: [], changedTouches: [finger(90, 190)] });
  assert.equal(gallery.main.src, '/second.webp');
});

test('single photo is unchanged by arrow event', () => {
  const gallery = makeGallery(1);
  gallery.next.fire('click');
  assert.equal(gallery.main.src, '/first.webp');
  assert.equal(gallery.counter.textContent, '1');
});
