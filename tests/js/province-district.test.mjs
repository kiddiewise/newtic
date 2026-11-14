import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { districtsForProvince, districtBelongsToProvince } from '../../resources/js/province-district-data.js';

const mapping = JSON.parse(readFileSync(new URL('../../resources/data/turkey-districts.json', import.meta.url), 'utf8'));

test('all 81 Turkish provinces and 973 districts are included', () => {
  assert.equal(Object.keys(mapping).length, 81);
  assert.equal(Object.values(mapping).reduce((total, list) => total + list.length, 0), 973);
});

test('Izmir supports Bornova but rejects a city pasted into district field', () => {
  const districts = districtsForProvince(mapping, 'İzmir');
  assert.ok(districts.includes('Bornova'));
  assert.ok(districts.includes('Gaziemir'));
  assert.ok(!districts.includes('İzmir'));
  assert.equal(districtBelongsToProvince(mapping, 'İzmir', 'Bornova'), true);
  assert.equal(districtBelongsToProvince(mapping, 'İzmir', 'İzmir'), false);
});

test('changing to Ankara invalidates previously selected Bornova', () => {
  assert.ok(districtsForProvince(mapping, 'Ankara').includes('Çankaya'));
  assert.equal(districtBelongsToProvince(mapping, 'Ankara', 'Bornova'), false);
  assert.equal(districtBelongsToProvince(mapping, 'Ankara', 'Çankaya'), true);
});

test('unknown and blank districts are never accepted', () => {
  assert.deepEqual(districtsForProvince(mapping, 'Atlantis'), []);
  assert.equal(districtBelongsToProvince(mapping, 'İzmir', ''), false);
  assert.equal(districtBelongsToProvince(mapping, '', 'Bornova'), false);
});
