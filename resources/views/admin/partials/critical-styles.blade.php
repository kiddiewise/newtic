<style>
/*
 * NEWTIC admin critical CSS is intentionally inlined.
 * If an old or missing Vite build is served, navigation and icons stay usable.
 * Rich visuals remain in the versioned frontend stylesheet.
 */
.admin-body{margin:0;background:#f6f8fb;color:#092037;font-family:"Montserrat",sans-serif;font-weight:400}
.admin-body *{box-sizing:border-box}
.admin-body svg.admin-icon{display:inline-block;width:20px;height:20px;flex:none;vertical-align:middle}
.admin-app-shell{display:grid;grid-template-columns:250px minmax(0,1fr);min-height:100vh}
.admin-sidebar{position:sticky;top:0;display:flex;flex-direction:column;height:100dvh;min-width:0;background:#092037;color:#e6ebf4;z-index:80}
.admin-sidebar-brand{display:flex;align-items:center;gap:12px;min-height:82px;padding:18px;color:#fff;text-decoration:none;font-size:19px;font-family:"Plus Jakarta Sans",sans-serif;font-weight:700}
.admin-sidebar-brand small{display:block;color:#b4cfd0;font-size:11px;font-weight:500}
.admin-sidebar-symbol{display:grid;place-items:center;flex:none;width:42px;height:42px;background:#424769;border-radius:12px}
.admin-sidebar-scroll{padding:9px 13px 22px;flex:1;min-height:0;overflow-y:auto}
.admin-menu-caption{margin:8px 10px 12px;font-size:10px;font-weight:500;letter-spacing:.09em;color:#9cbbc2}
.admin-menu-section{padding-top:18px;margin-top:14px;border-top:1px solid #ffffff25}
.admin-sidebar-nav{display:flex;flex-direction:column;gap:4px}
.admin-side-link{display:flex;align-items:center;gap:12px;min-height:42px;padding:9px 12px;border-radius:10px;font-size:12px;font-weight:500;text-decoration:none;color:#c9dedd}
.admin-side-link span{flex:1}
.admin-side-link.is-active{background:#f68220;color:#092037}
.admin-side-link:hover{background:#ffffff20;color:#fff}
.admin-side-count{display:inline-grid;place-items:center;min-width:21px;height:21px;border-radius:11px;background:#fff3e7;color:#6f3505;font-size:10px}
.admin-sidebar-bottom{padding:13px}
.admin-help-card{padding:14px;border-radius:12px;background:#424769;color:#f6f7fb}
.admin-help-card strong{display:block;font-size:12px}
.admin-help-card p{font-size:11px;line-height:1.45;margin:7px 0 10px}
.admin-help-card a{display:flex;align-items:center;gap:8px;color:#ffd3ac;font-size:12px;text-decoration:none}
.admin-help-icon{display:none}
.admin-sidebar-user{display:flex;gap:10px;align-items:center;margin-top:15px}
.admin-user-avatar,.admin-top-avatar{display:grid;place-items:center;flex:none;width:36px;height:36px;border-radius:50%;background:#424769;color:#fff;font-weight:800}
.admin-sidebar-user div{display:flex;flex-direction:column;min-width:0}
.admin-sidebar-user strong{font-size:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.admin-sidebar-user small{font-size:10px;color:#acd0d0}
.admin-workspace{min-width:0;display:flex;flex-direction:column}
.admin-topbar{position:sticky;top:0;z-index:50;background:#fff;border-bottom:1px solid #e5edef;padding:0 22px}
.admin-topbar-inner{display:flex;gap:14px;align-items:center;min-height:72px;max-width:none;margin:0}
.admin-topbar-heading{display:flex;flex-direction:column;gap:2px;flex:0 1 220px;min-width:0}
.admin-topbar-heading strong{font-size:13px}
.admin-topbar-heading small{font-size:11px;color:#81959d}
.admin-global-search{display:flex;align-items:center;gap:8px;flex:1;min-width:0;max-width:440px;padding:8px 12px;border:1px solid #e1e9ec;border-radius:11px;background:#f5f8f9}
.admin-global-search input{flex:1;min-width:0;border:0;background:transparent;outline:0;font-size:12px}
.admin-global-search button{border:0;background:transparent;color:#424769}
.admin-top-actions{display:flex;align-items:center;gap:9px;margin-left:auto}
.admin-top-icon-link{position:relative;display:grid;place-items:center;flex:none;width:38px;height:38px;border:1px solid #e5eef0;border-radius:10px;color:#38616a}
.admin-top-badge{position:absolute;right:-3px;top:-5px;border-radius:10px;padding:2px 5px;background:#f68220;color:#092037;font-size:10px}
.admin-menu-toggle{display:none;align-items:center;justify-content:center;width:39px;height:39px;border:1px solid #dfebeb;border-radius:10px;background:#fff;color:#21454e;cursor:pointer}
.admin-sidebar-backdrop[hidden]{display:none!important}
.admin-main{width:100%;max-width:1550px;flex:1;padding:28px 24px 65px;margin:0 auto}
.admin-footer{font-size:11px;text-align:center;color:#849ba1;padding:12px}
.admin-page-heading{display:flex;justify-content:space-between;gap:13px;align-items:center;margin-bottom:22px}
.admin-page-heading h1{font-family:"Plus Jakarta Sans",sans-serif;font-weight:700;font-size:clamp(24px,3vw,34px);line-height:1.2;margin:3px 0 8px}
.admin-page-heading p{font-size:13px;margin:0}
 .admin-eyebrow{color:#424769;font-size:10px;letter-spacing:.1em;font-weight:750}
.admin-panel,.admin-kpi{background:#fff;border:1px solid #e3ebee;border-radius:16px;padding:19px;margin-bottom:16px;box-shadow:0 8px 25px #284e5a0c}
.admin-panel h2{font-size:17px}
.admin-panel-title{display:flex;justify-content:space-between;align-items:center;gap:12px}
.admin-panel-title h2{margin:0}
.admin-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px;margin-bottom:17px}
.admin-kpi{display:flex;flex-direction:column;gap:8px;min-width:0;text-decoration:none;color:inherit}
.admin-kpi strong{font-family:"Plus Jakarta Sans",sans-serif;font-size:26px;font-weight:700}
.admin-kpi-label,.admin-kpi small,.admin-description{font-size:12px;color:#798d95}
.admin-kpi-icon{display:grid;place-items:center;flex:none;width:42px;height:42px;border-radius:11px;background:#fff1e4;color:#9e4d0f}
.admin-dashboard-grid{display:grid;grid-template-columns:minmax(0,1.7fr) minmax(260px,1fr);gap:14px}
.admin-dashboard-grid>.admin-panel{min-width:0}
.admin-action-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}
.admin-action-grid a{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:7px;border:1px solid #e5eef0;border-radius:12px;padding:14px 6px;text-align:center;text-decoration:none;color:#22545d;font-size:11px}
.admin-action-grid a span{display:grid;place-items:center}
.admin-primary,.admin-secondary,.admin-danger{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:42px;padding:10px 14px;border:0;border-radius:10px;font-size:12px;font-weight:600;cursor:pointer;text-decoration:none}
.admin-primary{background:#f68220;color:#092037}.admin-secondary{background:#f0f1f8;color:#424769}.admin-danger{background:#fff0ee;color:#b45247}
.admin-alert{padding:14px;margin-bottom:15px;border-radius:10px;font-size:13px}.admin-alert-success{background:#e2f5ec;color:#096a51}.admin-alert-error{background:#fff1ef;color:#ab4848}
.admin-state-pill{display:inline-block;border-radius:8px;background:#eef4f5;padding:5px 8px;font-size:11px}
.admin-data-table{width:100%;border-collapse:collapse;font-size:12px}
.admin-data-table td,.admin-data-table th{padding:10px;border-bottom:1px solid #eaf0f1;text-align:left}
.admin-table-scroll{overflow-x:auto}
.admin-body a:focus-visible,.admin-body button:focus-visible{outline:3px solid #d96810;outline-offset:2px}
@media(max-width:1000px){
 .admin-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
 .admin-dashboard-grid{grid-template-columns:1fr}
}
@media(max-width:900px){
 .admin-app-shell{display:block}
 .admin-sidebar{position:fixed;inset:0 auto 0 0;width:min(290px,86vw);height:100dvh;transform:translateX(-105%);transition:transform .2s ease;box-shadow:14px 0 38px #041f2c38}
 .admin-body.admin-sidebar-open .admin-sidebar{transform:translateX(0)}
 .admin-sidebar-backdrop{display:block;position:fixed;inset:0;z-index:70;background:#081b2d91;opacity:0;pointer-events:none}
 .admin-body.admin-sidebar-open .admin-sidebar-backdrop{opacity:1;pointer-events:auto}
 .admin-body.admin-sidebar-open{overflow:hidden}
 .admin-menu-toggle{display:flex}
}
@media(max-width:600px){
 .admin-topbar{padding:0 12px}.admin-topbar-inner{gap:10px;min-height:68px}
 .admin-topbar-heading{flex:1}.admin-topbar-heading small{display:none}
 .admin-global-search{position:absolute;top:68px;left:0;right:0;max-width:none;border-radius:0;background:#fff}
 .admin-top-icon-link{width:34px;height:34px}.admin-top-avatar{width:32px;height:32px}
 .admin-main{padding:68px 12px 42px}
 .admin-kpi-grid{gap:8px}.admin-kpi{padding:13px}.admin-kpi strong{font-size:21px}
 .admin-panel{padding:15px}.admin-action-grid{gap:6px}
}
@media(prefers-reduced-motion:reduce){.admin-sidebar{transition:none}}
</style>