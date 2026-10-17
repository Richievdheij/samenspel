@props(['slug'])

<svg {{ $attributes->class(['platform-icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($slug)
        @case('pc')
        @case('mac')
            <rect x="2" y="3" width="20" height="15" rx="2" /><path d="M8 22h8m-4-4v4" />
            @break
        @case('linux')
            <rect x="2" y="3" width="20" height="18" rx="2" /><path d="m6 9 3 3-3 3m6 0h6" />
            @break
        @case('steam-deck')
            <rect x="1.5" y="5" width="21" height="14" rx="4" /><rect x="7" y="7" width="10" height="10" rx="1" /><circle cx="4.5" cy="12" r="1" /><circle cx="19.5" cy="12" r="1" />
            @break
        @case('playstation')
            <path d="M5 8.5h14a3 3 0 0 1 2.9 2.4l.8 5.1a2 2 0 0 1-3.2 1.9l-2.5-2H7l-2.5 2a2 2 0 0 1-3.2-1.9l.8-5.1A3 3 0 0 1 5 8.5Z" /><path d="M6 11v3m-1.5-1.5h3" /><circle cx="17" cy="11.5" r=".5" /><path d="m18.5 13.5 1-1m-5.5 0h1" />
            @break
        @case('xbox')
            <circle cx="12" cy="12" r="10" /><path d="M5 5c4 1 10 8 14 14M19 5C15 6 9 13 5 19" />
            @break
        @case('switch')
            <rect x="3" y="3" width="18" height="18" rx="5" /><path d="M12 3v18" /><circle cx="8" cy="9" r="1.5" /><circle cx="16" cy="15" r="1.5" />
            @break
        @case('nintendo-other')
            <rect x="2" y="6" width="20" height="12" rx="6" /><path d="M7 10v4m-2-2h4" /><circle cx="16" cy="11" r=".5" /><circle cx="18" cy="13" r=".5" />
            @break
        @case('mobile')
            <rect x="6" y="2" width="12" height="20" rx="2" /><path d="M10 18h4" />
            @break
        @case('vr')
            <rect x="2" y="7" width="20" height="11" rx="4" /><path d="M2 11H1m21 0h1M8 18l1-3h6l1 3" />
            @break
        @default
            <rect x="3" y="3" width="18" height="18" rx="3" /><path d="M9 9h.01M15 9h.01M8 15h8" />
    @endswitch
</svg>
