/**
 * NEWTIC Intelligence admin visualization. Charts render from trusted server
 * data. No third-party scripts, tracking pixels or external chart services.
 */
const analyticsRoot = document.querySelector('[data-analytics-dashboard]');
if (analyticsRoot) {
  const chart = analyticsRoot.querySelector('[data-analytics-line-chart]');
  const dataNode = analyticsRoot.querySelector('[data-analytics-trend]');
  if (chart && dataNode) {
    let points = [];
    try { points = JSON.parse(dataNode.textContent || '[]'); } catch { /* no chart */ }
    const ns = 'http://www.w3.org/2000/svg';
    const add = (type, attrs, text) => {
      const node = document.createElementNS(ns, type);
      Object.entries(attrs).forEach(([key, value]) => node.setAttribute(key, String(value)));
      if (text != null) node.textContent = text;
      chart.appendChild(node);
      return node;
    };
    const width = 720, height = 245;
    const left = 34, right = 15, top = 22, bottom = 30;
    const max = Math.max(1, ...points.map(row => Number(row.views) || 0));
    const x = idx => left + (width - left - right) * idx / Math.max(1, points.length - 1);
    const y = value => top + (height - top - bottom) * (1 - value / max);
    for (let i = 0; i <= 4; i++) {
      const yPos = y(max * i / 4);
      add('line', { x1:left, y1:yPos, x2:width-right, y2:yPos, stroke:'#e6edf0', 'stroke-width':1 });
      add('text', { x:28, y:yPos+4, 'text-anchor':'end', fill:'#96a2a9', 'font-size':10 }, Math.round(max*i/4));
    }
    const drawLine = (key, color, fill) => {
      const plotted = points.map((row,i) => [x(i), y(Number(row[key]) || 0)]);
      if (!plotted.length) return;
      const coords = plotted.map(([a,b]) => a.toFixed(2)+','+b.toFixed(2)).join(' ');
      if (fill) {
        add('polygon', { points:coords+' '+x(points.length-1)+','+(height-bottom)+' '+x(0)+','+(height-bottom),
          fill, opacity:0.5 });
      }
      add('polyline', { points:coords, fill:'none', stroke:color, 'stroke-width':2.8,
        'stroke-linecap':'round', 'stroke-linejoin':'round' });
      if (points.length <= 10) {
        plotted.forEach(([a,b],i) => {
          add('circle', {cx:a,cy:b,r:3.5,fill:color,stroke:'#fff','stroke-width':1.5});
          add('title', {}, points[i].label+': '+(points[i][key]||0));
        });
      }
    };
    drawLine('views', '#315e85', '#dce8f4');
    drawLine('visitors', '#092037', null);
    points.forEach((row,i) => {
      if (i % Math.max(1, Math.ceil(points.length/7)) !== 0 && i !== points.length-1) return;
      add('text', {x:x(i), y:height-8, 'text-anchor':'middle', 'font-size':10, fill:'#839199'}, row.date);
    });
  }

  const liveSection = analyticsRoot.querySelector('[data-live-section]');
  const url = analyticsRoot.dataset.liveUrl;
  if (liveSection && url) {
    const tbody = liveSection.querySelector('[data-live-rows]');
    const count = liveSection.querySelector('[data-live-count]');
    const updated = liveSection.querySelector('[data-live-updated]');
    let refreshing = false;
    const refresh = async () => {
      if (document.hidden || refreshing) return;
      refreshing = true;
      try {
        const response = await fetch(url, { credentials:'same-origin',
          headers:{ Accept:'application/json', 'X-Requested-With':'XMLHttpRequest' },
          cache:'no-store' });
        if (!response.ok) return;
        const data = await response.json();
        if (!Array.isArray(data.visitors)) return;
        count.textContent = String(data.online ?? 0);
        if (updated) updated.textContent = data.updated_at || '';
        if (!tbody) return;
        tbody.replaceChildren();
        if (!data.visitors.length) {
          const row = tbody.insertRow();
          const cell = row.insertCell();
          cell.colSpan = 4;
          cell.className = 'analytics-empty';
          cell.textContent = 'Aktif oturum yok.';
          return;
        }
        data.visitors.forEach(visitor => {
          const row = tbody.insertRow();
          [visitor.page, visitor.device, visitor.location, (visitor.seconds_ago||0)+' sn önce']
            .forEach((item,index) => { const cell=row.insertCell(); cell.dataset.label=['Sayfa','Cihaz','Konum','Etkinlik'][index]; cell.textContent=String(item ?? ''); });
        });
      } catch { /* keep last verified snapshot on network failures */ }
      finally { refreshing = false; }
    };
    window.setInterval(refresh, 20000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
  }
}
