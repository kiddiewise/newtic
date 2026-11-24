import test from 'node:test';
import assert from 'node:assert/strict';
import {handoffIndex,rememberMotion,takeMotion,ordinaryNavigation,NAV_MOTION_KEY} from '../../resources/js/admin-nav-motion.js';
test('one-time same-tab navigation handoff preserves previous tab',()=>{
  const map=new Map(); const storage={setItem:(k,v)=>map.set(k,v),getItem:k=>map.get(k),removeItem:k=>map.delete(k)};
  rememberMotion(storage,'https://shop.test/admin/products','https://shop.test',0,1000);
  assert.equal(takeMotion(storage,'/admin/products',1500),0);
  assert.equal(takeMotion(storage,'/admin/products',1500),-1);
  rememberMotion(storage,'https://external.test/admin/products','https://shop.test',0,1000);
  assert.equal(map.has(NAV_MOTION_KEY),false);
});
test('invalid, expired or mismatched handoffs do not animate',()=>{
  const payload=JSON.stringify({index:3,path:'/admin/blog',at:1000});
  assert.equal(handoffIndex(payload,'/admin/blog',2000),3);
  assert.equal(handoffIndex(payload,'/admin/orders',2000),-1);
  assert.equal(handoffIndex(payload,'/admin/blog',14000),-1);
  assert.equal(handoffIndex(payload,'/admin/blog',999),-1);
  assert.equal(handoffIndex('broken','/admin/blog',2000),-1);
  assert.equal(handoffIndex(JSON.stringify({index:9,path:'/admin/blog',at:1000}),'/admin/blog',2000),-1);
  assert.equal(takeMotion(undefined,'/admin/blog'),-1);
});
test('modified links and prevented navigation are not intercepted',()=>{
  assert.equal(ordinaryNavigation({button:0}),true);
  assert.equal(ordinaryNavigation({button:0,ctrlKey:true}),false);
  assert.equal(ordinaryNavigation({button:0,defaultPrevented:true}),false);
  assert.equal(ordinaryNavigation({button:1}),false);
});
