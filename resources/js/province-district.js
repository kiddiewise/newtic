import { districtsForProvince, districtBelongsToProvince } from './province-district-data.js';

/*
 * Match city and district across Chrome address autofill, saved addresses and
 * manual edits. The select contains ONLY districts in the selected province.
 * Laravel repeats this check on every POST — JS cannot be trusted alone.
 */
document.querySelectorAll('[data-tr-address-fields]').forEach((group) => {
  const province = group.querySelector('select[data-tr-province]');
  const district = group.querySelector('select[data-tr-district]');
  const source = group.querySelector('script[data-tr-district-map]');
  const hint = group.querySelector('[data-tr-district-hint]');
  if (!province || !district || !source) return;

  let mapping;
  try {
    mapping = JSON.parse(source.textContent);
  } catch (error) {
    // Keep the server-rendered options if an asset was corrupted.
    return;
  }

  const validityMessage = 'Seçilen ilçe, il ile eşleşmiyor. Lütfen doğru ilçeyi seçin.';

  const validate = () => {
    const valid = !district.value || districtBelongsToProvince(mapping, province.value, district.value);
    district.setCustomValidity(valid ? '' : validityMessage);
    return valid && district.value !== '';
  };

  const refresh = (preserve) => {
    const previous = preserve ? district.value : '';
    const allowed = districtsForProvince(mapping, province.value);
    const placeholder = new Option(province.value ? 'İlçe seçin' : 'Önce il seçin', '');

    district.replaceChildren(placeholder, ...allowed.map((name) => new Option(name, name)));
    district.disabled = !province.value;
    district.value = districtBelongsToProvince(mapping, province.value, previous) ? previous : '';
    district.setCustomValidity('');
    if (hint) {
      hint.textContent = province.value
        ? 'Seçtiğiniz ile bağlı ilçeler listeleniyor.'
        : 'İl seçildiğinde ilçeler listelenecek.';
    }

    // Inform the checkout's existing locked/unlocked payment button controller.
    district.dispatchEvent(new Event('change', { bubbles: true }));
  };

  province.addEventListener('input', () => refresh(false));
  province.addEventListener('change', () => refresh(false));
  district.addEventListener('change', validate);
  province.form?.addEventListener('submit', (event) => {
    if (!validate()) {
      event.preventDefault();
      district.reportValidity();
    }
  });
  province.form?.addEventListener('focusin', validate);
  window.addEventListener('pageshow', () => refresh(true));
  refresh(true);
});

/** Used by checkout's payment-button gate to catch autofill without change. */
export function checkoutHasValidDistrict(form) {
  const group = form?.querySelector('[data-tr-address-fields]');
  if (!group) return true;
  const province = group.querySelector('[data-tr-province]');
  const district = group.querySelector('[data-tr-district]');
  const source = group.querySelector('[data-tr-district-map]');
  if (!province || !district || !source) return true;
  try {
    const valid = districtBelongsToProvince(JSON.parse(source.textContent), province.value, district.value);
    district.setCustomValidity(district.value && !valid
      ? 'Seçilen ilçe, il ile eşleşmiyor. Lütfen listeden doğru ilçeyi seçin.' : '');
    return valid;
  } catch {
    district.setCustomValidity('İlçe listesi yüklenemedi. Sayfayı yenileyin.');
    return false;
  }
}
