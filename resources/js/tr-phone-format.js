/**
 * Türkiye-only mobile field, with a fixed visual +90 prefix.
 * Accept pasted 05..., 5..., +90 5..., 905... and 0090 5...
 * and return only the national 10 digits for on-screen formatting.
 */
export function turkishMobileDigits(value) {
  const raw = String(value ?? '').trim();
  let digits = raw.replace(/\D/g, '');

  if (digits.startsWith('0090') && digits.length > 4) {
    digits = digits.slice(4);
  } else if (digits.startsWith('90') &&
    (raw.replace(/[\s().-]/g, '').startsWith('+90') || digits.length >= 12)) {
    digits = digits.slice(2);
  }

  if (digits.startsWith('05')) digits = digits.slice(1);
  return digits.slice(0, 10);
}

export function formatTurkishMobile(value) {
  const digits = turkishMobileDigits(value);
  return [
    digits.slice(0, 3),
    digits.slice(3, 6),
    digits.slice(6, 8),
    digits.slice(8, 10),
  ].filter(Boolean).join(' ');
}

export function isTurkishMobileComplete(value) {
  return /^5\d{9}$/.test(turkishMobileDigits(value));
}

/** Cursor offset after the same count of entered digits (ignoring spaces). */
export function caretForMobileDigits(value, digitCount) {
  if (digitCount <= 0) return 0;
  let seen = 0;
  for (let i = 0; i < value.length; i += 1) {
    if (/\d/.test(value[i]) && ++seen >= digitCount) return i + 1;
  }
  return value.length;
}
