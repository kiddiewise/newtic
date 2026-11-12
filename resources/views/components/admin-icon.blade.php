@props(['name' => 'grid'])
<svg {{ $attributes->class(['admin-icon']) }} width="20" height="20" viewBox="0 0 24 24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
@switch($name)
@case('home')<path d="m3 10 9-7 9 7v10H3z"/><path d="M9 20v-7h6v7"/>@break
@case('grid')<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>@break
@case('layout')<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 9v12"/>@break
@case('bag')<rect x="4" y="8" width="16" height="13" rx="2"/><path d="M9 9V6a3 3 0 0 1 6 0v3"/>@break
@case('star')<path d="m12 3 2.9 5.8 6.4.9-4.6 4.5 1.1 6.4L12 17.6l-5.8 3 1.1-6.4L2.7 9.7l6.4-.9L12 3Z"/>@break
@case('package')<path d="m3 7 9-4 9 4-9 4-9-4Z"/><path d="M3 7v10l9 4 9-4V7M12 11v10"/>@break
@case('chart')<path d="M3 21h18M6 17v-6M11 17V6M16 17v-9M21 17V3"/>@break
@case('clock')<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>@break
@case('calendar')<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4M17 3v4M3 10h18"/>@break
@case('users')<circle cx="9" cy="8" r="3"/><path d="M3 20v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M17 15a5 5 0 0 1 4 5"/>@break
@case('image')<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m3 17 5-5 4 4 3-3 6 5"/>@break
@case('refresh-cw')<path d="M20 11a8 8 0 0 0-14-5L3 9M3 4v5h5M4 13a8 8 0 0 0 14 5l3-3M21 20v-5h-5"/>@break
@case('truck')<path d="M2 6h13v11H2zM15 10h4l3 3v4h-7"/><circle cx="6" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>@break
@case('settings')<circle cx="12" cy="12" r="3"/><path d="M19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a8 8 0 0 0-1.7-1L14.5 3h-4L10 6a7 7 0 0 0-1.7 1l-2.4-1-2 3.5L6 11a7 7 0 0 0 0 2l-2.1 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7 1l.5 3h4l.5-3a7 7 0 0 0 1.7-1l2.4 1 2-3.5-2-1.5a7 7 0 0 0 .1-1Z"/>@break
@case('landmark')<path d="m3 10 9-7 9 7M4 10h16M5 21h14M7 10v11M12 10v11M17 10v11"/>@break
@case('check')<path d="m5 12 5 5L20 7"/>@break
@case('plus')<path d="M12 5v14M5 12h14"/>@break
@case('arrow-right')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
@case('external-link')<path d="M13 5h6v6M19 5l-9 9"/><path d="M19 13v5a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h5"/>@break
@case('menu')<path d="M4 6h16M4 12h16M4 18h16"/>@break
@case('close')<path d="M5 5 19 19M19 5 5 19"/>@break
@case('search')<circle cx="11" cy="11" r="7"/><path d="m16 16 5 5"/>@break
@case('bell')<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>@break
@case('logout')<path d="M10 3H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h5M14 7l5 5-5 5M7 12h12"/>@break
@case('info')<circle cx="12" cy="12" r="9"/><path d="M12 11v6M12 7h.01"/>@break
@case('layers')<path d="m12 3 9 5-9 5-9-5 9-5ZM3 12l9 5 9-5M3 16l9 5 9-5"/>@break
@case('mail')<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>@break
@case('alert')<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v4M12 17h.01"/>@break
@default<circle cx="12" cy="12" r="9"/>@break
@endswitch
</svg>