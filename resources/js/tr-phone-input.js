import {
  turkishMobileDigits,
  formatTurkishMobile,
  caretForMobileDigits,
} from './tr-phone-format.js';

/* Accessible, zero-dependency progressive enhancement for shared address fields. */
document.querySelectorAll('[data-tr-mobile-phone]').forEach((input) => {
  const formatInput = () => {
    const raw = input.value;
    const previousCaret = input.selectionStart ?? raw.length;
    const digitsBeforeCaret = turkishMobileDigits(raw.slice(0, previousCaret)).length;
    const formatted = formatTurkishMobile(raw);
    if (raw === formatted) return;
    input.value = formatted;

    // Preserve cursor position when editing a middle digit or pasting a number.
    if (document.activeElement === input && typeof input.setSelectionRange === 'function') {
      const cursor = caretForMobileDigits(formatted, digitsBeforeCaret);
      input.setSelectionRange(cursor, cursor);
    }
  };

  input.addEventListener('beforeinput', (event) => {
    if (event.inputType !== 'deleteContentBackward' || !event.cancelable) return;
    const from = input.selectionStart;
    const to = input.selectionEnd;
    if (from === null || to !== from || from < 2 || input.value[from - 1] !== ' ') return;

    // Backspace immediately after a grouping space deletes the previous digit.
    event.preventDefault();
    const original = input.value;
    const remaining = original.slice(0, from - 2) + original.slice(from);
    const digits = turkishMobileDigits(original.slice(0, from - 2)).length;
    input.value = formatTurkishMobile(remaining);
    const cursor = caretForMobileDigits(input.value, digits);
    input.setSelectionRange(cursor, cursor);
    input.dispatchEvent(new Event('input', { bubbles: true }));
  });

  input.addEventListener('input', formatInput);
  input.addEventListener('change', formatInput);
  formatInput();
});
