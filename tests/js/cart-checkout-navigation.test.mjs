import test from 'node:test';
import assert from 'node:assert/strict';
import { checkoutCartNavigation } from '../../resources/js/cart-checkout-navigation.js';

const cart = 'https://powderblue-dinosaur-538060.hostingersite.com/sepet';

test('cart -> checkout -> back uses the existing cart history entry', () => {
  assert.equal(checkoutCartNavigation(cart, cart, 3), 'back');
  assert.equal(checkoutCartNavigation(cart + '/', cart, 3), 'back');
  assert.equal(checkoutCartNavigation(cart + '?source=order', cart, 2), 'back');
});

test('direct checkout, missing referrer and privacy restrictions replace checkout', () => {
  assert.equal(checkoutCartNavigation('', cart, 4), 'replace');
  assert.equal(checkoutCartNavigation(cart, cart, 1), 'replace');
  assert.equal(checkoutCartNavigation('invalid-url', cart, 3), 'replace');
  assert.equal(checkoutCartNavigation('https://google.com/sepet', cart, 5), 'replace');
});

test('a checkout or another internal page must never be mistaken for prior cart', () => {
  assert.equal(checkoutCartNavigation('https://powderblue-dinosaur-538060.hostingersite.com/odeme', cart, 4), 'replace');
  assert.equal(checkoutCartNavigation('https://powderblue-dinosaur-538060.hostingersite.com/', cart, 4), 'replace');
  assert.equal(checkoutCartNavigation('https://powderblue-dinosaur-538060.hostingersite.com/sepetim', cart, 4), 'replace');
});
