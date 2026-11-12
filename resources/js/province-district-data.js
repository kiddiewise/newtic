/* Pure, framework-independent helpers for Turkey's province/district lists. */
export function districtsForProvince(mapping, province) {
  const list = mapping?.[province];
  return Array.isArray(list) ? list : [];
}

export function districtBelongsToProvince(mapping, province, district) {
  return typeof district === 'string' && district !== ''
    && districtsForProvince(mapping, province).includes(district);
}
