import test from 'node:test';
import assert from 'node:assert/strict';
import {
  turkishMobileDigits,
  formatTurkishMobile,
  isTurkishMobileComplete,
  caretForMobileDigits,
} from '../../resources/js/tr-phone-format.js';

test('groups as 3-3-2-2 while typing', () => {
  assert.equal(formatTurkishMobile('5'), '5');
  assert.equal(formatTurkishMobile('5555'), '555 5');
  assert.equal(formatTurkishMobile('55555555'), '555 555 55');
  assert.equal(formatTurkishMobile('5555555555'), '555 555 55 55');
  assert.equal(formatTurkishMobile('555555555599'), '555 555 55 55');
  assert.equal(formatTurkishMobile(''), '');
});
test('accepts national and international formats from paste or saved addresses', () => {
  for (const input of [
    '05555555555',
    '0555 555 55 55',
    '+90 555 555 55 55',
    '+905555555555',
    '905555555555',
    '0090 555 555 55 55',
    '555-555-55-55',
    '(555) 555 55 55',
  ]) {
    assert.equal(formatTurkishMobile(input), '555 555 55 55', input);
    assert.equal(turkishMobileDigits(input), '5555555555', input);
  }
});
test('rejects incomplete and fixed-line numbers', () => {
  assert.equal(isTurkishMobileComplete('555 555 55 55'), true);
  assert.equal(isTurkishMobileComplete('532 123 45 67'), true);
  assert.equal(isTurkishMobileComplete('555 555 55'), false);
  assert.equal(isTurkishMobileComplete('232 123 45 67'), false);
  assert.equal(isTurkishMobileComplete(''), false);
});
test('typing and caret movement preserve a middle edit', () => {
  assert.equal(caretForMobileDigits('555 555 55 55', 1), 1);
  assert.equal(caretForMobileDigits('555 555 55 55', 3), 3);
  assert.equal(caretForMobileDigits('555 555 55 55', 4), 5);
  assert.equal(caretForMobileDigits('555 555 55 55', 10), 13);
  assert.equal(caretForMobileDigits('555 555 55 55', 0), 0);
});
