/* NEWTIC admin only: never cache authenticated HTML, orders or customer data. */
const ORDERS = '/admin/orders?unread=1';
self.addEventListener('install', event => event.waitUntil(self.skipWaiting()));
self.addEventListener('activate', event => event.waitUntil(self.clients.claim()));
self.addEventListener('fetch', event => {
  const url = new URL(event.request.url);
  if (event.request.mode !== 'navigate' || event.request.method !== 'GET' ||
      url.origin !== self.location.origin || !url.pathname.startsWith('/admin/')) return;
  event.respondWith(fetch(event.request).catch(() => new Response(
    '<!doctype html><html lang="tr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>NEWTIC</title><body style="font:16px system-ui;padding:28px;color:#092037"><h1>Bağlantı yok</h1><p>Güvenlik nedeniyle sipariş ve yönetim verileri çevrimdışı saklanmaz.</p><a href="/admin/dashboard">Tekrar dene</a></body></html>',
    {headers:{'Content-Type':'text/html; charset=utf-8','Cache-Control':'no-store'}}
  )));
});
self.addEventListener('push', event => {
  event.waitUntil(self.registration.showNotification('NEWTIC Yönetim', {
    body:'Yeni sipariş bildiriminiz var. Yönetim panelini kontrol edin.',
    icon:'/newtic-admin-192.png',badge:'/newtic-admin-192.png',
    tag:'newtic-order',renotify:true,data:{url:ORDERS}
  }));
});
self.addEventListener('notificationclick', event => {
  event.notification.close();
  event.waitUntil((async () => {
    const target = new URL(ORDERS,self.location.origin).href;
    const windows = await self.clients.matchAll({type:'window',includeUncontrolled:true});
    const client = windows.find(c => c.url === target);
    if (client) return client.focus();
    return self.clients.openWindow(target);
  })());
});
