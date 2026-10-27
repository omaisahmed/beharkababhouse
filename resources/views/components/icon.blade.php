@props(['name', 'class' => 'w-5 h-5'])

<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" {{ $attributes->merge(['class' => $class]) }} aria-hidden="true">
    @switch($name)
        @case('dashboard')
            <rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>
            @break
        @case('pos')
            <rect x="3" y="4" width="18" height="12" rx="2"/><path d="M7 20h10M9 16v4M15 16v4"/><path d="M7 8h6M7 11h3"/>
            @break
        @case('receipt')
            <path d="M5 3h14v18l-3-2-3 2-3-2-3 2-2-1.5V3Z"/><path d="M8 7h8M8 11h8M8 15h5"/>
            @break
        @case('orders')
            <path d="M8 3h8l1 3h2v15H5V6h2l1-3Z"/><path d="M9 9h6M9 13h6M9 17h4"/>
            @break
        @case('kitchen')
            <path d="M4 20h16M6 20V9a3 3 0 0 1 3-3h6a3 3 0 0 1 3 3v11"/><path d="M9 6V3h6v3M9 12h6"/>
            @break
        @case('tables')
            <path d="M3 9h18M5 9l1.5 11M19 9l-1.5 11M8 4h8v5H8z"/><path d="M9 14h6"/>
            @break
        @case('menu')
            <path d="M4 5h16M4 12h16M4 19h10"/>
            @break
        @case('categories')
            <rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>
            @break
        @case('products')
            <path d="M4 8.5 12 4l8 4.5v7L12 20l-8-4.5v-7Z"/><path d="m4 8.5 8 4.5 8-4.5M12 13v7"/>
            @break
        @case('modifiers')
            <path d="M4 6h16M4 12h16M4 18h16"/><circle cx="9" cy="6" r="2"/><circle cx="15" cy="12" r="2"/><circle cx="8" cy="18" r="2"/>
            @break
        @case('inventory')
            <path d="M3 9 12 4l9 5v10l-9 5-9-5V9Z"/><path d="m3 9 9 5 9-5M12 14v10"/><path d="M7 6.5 16 11"/>
            @break
        @case('recipe')
            <path d="M6 3h12v18H6z"/><path d="M9 7h6M9 11h6M9 15h4"/>
            @break
        @case('movements')
            <path d="M4 7h13l-3-3M20 17H7l3 3"/><path d="M4 7v10M20 17V7"/>
            @break
        @case('supplier')
            <path d="M3 17h13V6H3v11Z"/><path d="M16 9h3l2 3v5h-5"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>
            @break
        @case('purchase')
            <path d="M6 6h15l-1.5 8H8L6 3H3"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/><path d="M8 10h11"/>
            @break
        @case('shift')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('expenses')
            <path d="M3 7h18v12H3z"/><path d="M3 10h18M7 15h4"/><path d="M16 3v4M8 3v4"/>
            @break
        @case('reports')
            <path d="M4 20V4M4 20h16"/><rect x="7" y="11" width="3" height="6" rx="1"/><rect x="12" y="7" width="3" height="10" rx="1"/><rect x="17" y="4" width="3" height="13" rx="1"/>
            @break
        @case('users')
            <circle cx="9" cy="8" r="3.5"/><path d="M3 20a6 6 0 0 1 12 0"/><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 20a6 6 0 0 0-2-4.5"/>
            @break
        @case('roles')
            <path d="M12 3 4 6v6c0 4.5 3.4 7.7 8 9 4.6-1.3 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('settings')
            <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.9 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.9.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.9-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.9V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>
            @break
        @case('backup')
            <path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/><path d="M12 8v5l3 2"/>
            @break
        @case('audit')
            <path d="M4 4h16v16H4z"/><path d="M8 8h8M8 12h8M8 16h5"/>
            @break
        @case('info')
            <circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>
            @break
        @case('search')
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14"/>
            @break
        @case('minus')
            <path d="M5 12h14"/>
            @break
        @case('trash')
            <path d="M4 7h16M9 7V4h6v3M6 7l1 14h10l1-14M10 11v6M14 11v6"/>
            @break
        @case('edit')
            <path d="M4 20h4l11-11-4-4L4 16v4Z"/><path d="m13.5 6.5 4 4"/>
            @break
        @case('x')
            <path d="M6 6l12 12M18 6 6 18"/>
            @break
        @case('check')
            <path d="m4 12 5 5L20 6"/>
            @break
        @case('printer')
            <path d="M7 8V3h10v5"/><rect x="4" y="8" width="16" height="9" rx="2"/><path d="M7 14h10v7H7z"/>
            @break
        @case('download')
            <path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 19h16"/>
            @break
        @case('upload')
            <path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 19h16"/>
            @break
        @case('clock')
            <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>
            @break
        @case('arrow-up')
            <path d="M12 20V5M6 11l6-6 6 6"/>
            @break
        @case('arrow-down')
            <path d="M12 4v15M6 13l6 6 6-6"/>
            @break
        @case('chevron-left')
            <path d="m14 6-6 6 6 6"/>
            @break
        @case('chevron-right')
            <path d="m10 6 6 6-6 6"/>
            @break
        @case('chevron-down')
            <path d="m6 9 6 6 6-6"/>
            @break
        @case('logout')
            <path d="M9 4H4v16h5"/><path d="M15 8l4 4-4 4M19 12H9"/>
            @break
        @case('sun')
            <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            @break
        @case('moon')
            <path d="M21 13A8.5 8.5 0 1 1 11 3a6.5 6.5 0 0 0 10 10Z"/>
            @break
        @case('hamburger')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('alert')
            <path d="M12 3 2.5 20h19L12 3Z"/><path d="M12 9v5M12 17h.01"/>
            @break
        @case('cash')
            <rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/><path d="M6 9v6M18 9v6"/>
            @break
        @case('card')
            <rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20M6 15h4"/>
            @break
        @case('wallet')
            <path d="M4 6h14a2 2 0 0 1 2 2v10H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h13"/><path d="M16 12h4v3h-4z"/>
            @break
        @case('phone')
            <path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2Z"/>
            @break
        @case('map-pin')
            <path d="M12 21s7-6 7-12a7 7 0 1 0-14 0c0 6 7 12 7 12Z"/><circle cx="12" cy="9" r="2.5"/>
            @break
        @case('tag')
            <path d="m20 12-8 8L3 11V3h8l9 9Z"/><circle cx="7.5" cy="7.5" r="1.2"/>
            @break
        @case('filter')
            <path d="M3 5h18l-7 8v6l-4 2v-8L3 5Z"/>
            @break
        @case('refresh')
            <path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 4v5h-5"/>
            @break
        @case('save')
            <path d="M4 4h13l3 3v13H4z"/><path d="M8 4v6h8V4M8 15h8v5H8z"/>
            @break
        @case('eye')
            <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/>
            @break
        @case('ban')
            <circle cx="12" cy="12" r="9"/><path d="m5.6 5.6 12.8 12.8"/>
            @break
        @case('undo')
            <path d="M9 5 4 10l5 5"/><path d="M4 10h10a5 5 0 0 1 0 10h-2"/>
            @break
        @case('list')
            <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
            @break
        @case('grid')
            <rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="8" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/><rect x="13" y="13" width="8" height="8" rx="1"/>
            @break
        @case('external')
            <path d="M14 4h6v6M20 4l-9 9"/><path d="M19 14v6H4V5h6"/>
            @break
        @case('shield')
            <path d="M12 3 4 6v6c0 4.5 3.4 7.7 8 9 4.6-1.3 8-4.5 8-9V6l-8-3Z"/>
            @break
        @case('database')
            <ellipse cx="12" cy="5" rx="8" ry="3"/><path d="M4 5v6c0 1.7 3.6 3 8 3s8-1.3 8-3V5M4 11v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"/>
            @break
        @case('file')
            <path d="M6 3h8l4 4v14H6z"/><path d="M14 3v5h4M9 13h6M9 17h6"/>
            @break
        @case('home')
            <path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M10 20v-6h4v6"/>
            @break
        @case('bell')
            <path d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/>
            @break
        @case('user')
            <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
            @break
        @case('lock')
            <rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>
            @break
        @case('mail')
            <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>
            @break
        @case('building')
            <path d="M4 21V4h10v17M14 9h6v12M8 8h2M8 12h2M8 16h2M17 13h1M17 17h1"/>
            @break
        @case('sparkles')
            <path d="m12 3 1.8 4.2L18 9l-4.2 1.8L12 15l-1.8-4.2L6 9l4.2-1.8L12 3Z"/><path d="M19 15l.9 2.1L22 18l-2.1.9L19 21l-.9-2.1L16 18l2.1-.9L19 15Z"/>
            @break
        @case('percent')
            <path d="M19 5 5 19"/><circle cx="7" cy="7" r="2"/><circle cx="17" cy="17" r="2"/>
            @break
        @case('truck')
            <path d="M3 16V6h11v10M14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="18" cy="18" r="2"/>
            @break
        @case('box')
            <path d="M3 8 12 3l9 5v8l-9 5-9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/>
            @break
        @case('scale')
            <path d="M12 3v18M7 21h10M5 7h14"/><path d="M5 7 2 14h6L5 7ZM19 7l-3 7h6l-3-7Z"/>
            @break
        @case('split')
            <path d="M4 5h5l6 7h5M4 19h5l6-7"/><path d="m17 9 3 3-3 3"/>
            @break
        @case('bluetooth')
            <path d="m7 7 10 10-5 4V3l5 4L7 17"/>
            @break
        @case('wifi-off')
            <path d="M3 3l18 18M8.5 8.5A11 11 0 0 1 21 9M2 9a15 15 0 0 1 4-2M5 12a10 10 0 0 1 3-1.5M8.5 15.5a5 5 0 0 1 5 0"/><path d="M12 20h.01"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
