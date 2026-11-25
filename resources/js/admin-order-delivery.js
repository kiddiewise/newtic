const dialog = document.querySelector('[data-delivery-editor]');
const trigger = document.querySelector('[data-delivery-edit-open]');
if (dialog && trigger && typeof dialog.showModal === 'function') {
  const form=dialog.querySelector('form');
  const open=()=>{if(!dialog.open) dialog.showModal();};
  const close=()=>dialog.close();
  trigger.addEventListener('click',open);
  dialog.querySelectorAll('[data-delivery-edit-close]').forEach(button=>button.addEventListener('click',close));
  dialog.addEventListener('click',event=>{if(event.target===dialog)close();});
  dialog.addEventListener('close',()=>trigger.focus({preventScroll:true}));
  form?.addEventListener('submit',()=>{const save=form.querySelector('button[type="submit"]');if(save){save.disabled=true;save.textContent='Kaydediliyor…';}});
  window.addEventListener('pageshow',()=>{const save=form?.querySelector('button[type="submit"]');if(save){save.disabled=false;save.textContent='Kaydet';}});
  if(dialog.hasAttribute('data-delivery-reopen'))open();
}
