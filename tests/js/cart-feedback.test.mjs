import test from 'node:test';
import assert from 'node:assert/strict';
import {createDismissal,sendCartRequest} from '../../resources/js/cart-feedback.js';
const receipt='b3ebc962-a8f8-4e28-b14c-df1fd5d36150';
const state={ok:true,count:1,product_quantities:{1:1},subtotal_label:'10,00 ₺'};
const options={operation:receipt,statusUrl:'https://shop.example/sepet/durum'};

test('repeated messages cancel the old timer and fade before hiding',()=>{
  let id=0;const queue=new Map();
  const dismiss=createDismissal(fn=>{queue.set(++id,fn);return id;},key=>queue.delete(key));
  const classes=new Set();const element={hidden:true,classList:{add:x=>classes.add(x),remove:x=>classes.delete(x)}};
  const tick=()=>{const [key,fn]=queue.entries().next().value;queue.delete(key);fn();};
  dismiss(element);assert.equal(element.hidden,false);
  tick();assert.ok(classes.has('is-feedback-leaving'));
  dismiss(element);assert.equal(classes.size,0);assert.equal(queue.size,1);
  tick();assert.equal(element.hidden,false);
  tick();assert.equal(element.hidden,true);assert.equal(queue.size,0);
});

test('successful write does not issue a second request',async()=>{
  let calls=0;const data=new FormData();
  const result=await sendCartRequest('/sepet/ekle',data,{...options,fetcher:async()=>{calls++;return {ok:true,json:async()=>state};}});
  assert.equal(calls,1);assert.equal(data.get('cart_operation'),receipt);assert.equal(result.count,1);
});

test('lost write response recovers only from a matching receipt, never retries POST',async()=>{
  const methods=[];
  const result=await sendCartRequest('/sepet/ekle',new FormData(),{...options,fetcher:async(url,init)=>{
    methods.push(init.method);
    if(init.method==='POST')throw new TypeError('Failed to fetch');
    assert.ok(url.includes(receipt));return {ok:true,json:async()=>({...state,operation_confirmed:true})};
  }});
  assert.deepEqual(methods,['POST','GET']);assert.equal(result.count,1);
});

test('unknown result is not claimed successful and still refreshes cart counts',async()=>{
  const methods=[];
  await assert.rejects(sendCartRequest('/sepet/ekle',new FormData(),{...options,fetcher:async(url,init)=>{
    methods.push(init.method);
    if(init.method==='POST')return {ok:false,status:504,json:async()=>{throw Error('HTML');}};
    return {ok:true,json:async()=>({...state,operation_confirmed:false})};
  }}),err=>err.snapshot.count===1 && !err.message.includes('fetch'));
  assert.deepEqual(methods,['POST','GET']);
});

test('validation errors stay readable and do not trigger recovery',async()=>{
  let count=0;
  await assert.rejects(sendCartRequest('/sepet/ekle',new FormData(),{...options,fetcher:async()=>{
    count++;return {ok:false,status:422,json:async()=>({errors:{items:['Stok yetersiz.']}})};
  }}),/Stok yetersiz/);
  assert.equal(count,1);
});
