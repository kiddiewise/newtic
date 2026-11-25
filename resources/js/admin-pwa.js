const manifest = document.querySelector('link[rel="manifest"][href*="newtic-admin"]');
const panel = document.querySelector('[data-admin-pwa]');
let promptEvent;
window.addEventListener('beforeinstallprompt', e => {
  if (!manifest) return;
  e.preventDefault(); promptEvent=e;
  const button=panel?.querySelector('[data-pwa-install]');
  if (button) button.hidden=false;
});
if (manifest && 'serviceWorker' in navigator && window.isSecureContext) {
  const registration=navigator.serviceWorker.register('/admin/pwa-worker.js',{scope:'/admin/'});
  registration.catch(()=>{});
  if (panel) setup(registration);
} else if(panel) {
  panel.querySelector('[data-pwa-state]').textContent='Bu tarayıcı güvenli PWA bağlantısını desteklemiyor.';
}
function setup(registration) {
  const state=panel.querySelector('[data-pwa-state]');
  const enable=panel.querySelector('[data-pwa-enable]');
  const disable=panel.querySelector('[data-pwa-disable]');
  const test=panel.querySelector('[data-pwa-test]');
  const install=panel.querySelector('[data-pwa-install]');
  const help=panel.querySelector('[data-pwa-install-help]');
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content;
  const installed=matchMedia('(display-mode: standalone)').matches || navigator.standalone===true;
  if(installed) help.textContent='Uygulama ana ekrandan açıldı.';
  install.addEventListener('click',async()=>{
    if(!promptEvent)return;
    await promptEvent.prompt(); await promptEvent.userChoice; promptEvent=null;install.hidden=true;
  });
  window.addEventListener('appinstalled',()=>{install.hidden=true;help.textContent='Uygulama yüklendi.';});
  const supported='PushManager' in window && 'Notification' in window;
  if(!supported){state.textContent='Bu cihazda Web Push desteklenmiyor. iPhone/iPad’de önce uygulamayı ana ekrana ekleyip oradan aç.';return;}
  async function request(url,method='GET',body) {
    const response=await fetch(url,{method,credentials:'same-origin',cache:'no-store',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},...(body?{body:JSON.stringify(body)}:{})});
    if(!response.ok) throw Error('request');
    return response.json();
  }
  let reg, config;
  const controls=(active,busy=false)=>{
    enable.disabled=busy || active || Notification.permission==='denied';
    disable.disabled=busy || !active;test.disabled=busy || !active;
  };
  async function refresh() {
    try {
      reg=await registration; config=await request(panel.dataset.status);
      const sub=await reg.pushManager.getSubscription();
      const active=Boolean(config.active && sub && Notification.permission==='granted');
      controls(active);
      state.textContent=active?'Bildirimler bu oturumda açık.':Notification.permission==='denied'?'Bildirim izni engellenmiş. Tarayıcı veya telefon ayarlarından aç.':'Bildirimler kapalı.';
    } catch { controls(false,true);state.textContent='Bildirim servisine bağlanılamadı.'; }
  }
  const keyBytes=key=>Uint8Array.from(atob(key.replace(/-/g,'+').replace(/_/g,'/')+'='.repeat((4-key.length%4)%4)),c=>c.charCodeAt(0));
  enable.addEventListener('click',async()=>{
    controls(false,true);
    try {
      // Request permission directly in the user's click handler, before network calls.
      const permission=await Notification.requestPermission();
      if(permission!=='granted'){await refresh();return;}
      reg=await navigator.serviceWorker.ready;
      let sub=await reg.pushManager.getSubscription();
      if(!sub) sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:keyBytes(config.public_key)});
      await request(panel.dataset.subscribe,'POST',{endpoint:sub.endpoint});
      await refresh();
    }catch{controls(false);state.textContent='Bildirim açılamadı. Bağlantıyı ve cihaz iznini kontrol et.';}
  });
  disable.addEventListener('click',async()=>{
    controls(true,true);
    try {
      await request(panel.dataset.subscribe,'DELETE');
      const sub=await reg.pushManager.getSubscription();if(sub) await sub.unsubscribe();
      await refresh();
    }catch{await refresh();state.textContent='Bildirim kapatma işlemi tamamlanamadı; yeniden dene.';}
  });
  test.addEventListener('click',async()=>{
    controls(true,true);
    try{await request(panel.dataset.test,'POST',{});state.textContent='Test bildirimi sağlayıcı tarafından kabul edildi. Telefonunu kontrol et.';}
    catch{state.textContent='Test bildirimi gönderilemedi. İzinleri ve sunucu bağlantısını kontrol et.';}
    finally{controls(true);}
  });
  refresh();
}
