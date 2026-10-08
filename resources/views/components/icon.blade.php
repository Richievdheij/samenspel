{{--
    <x-icon>, a line icon from Lucide (lucide.dev, ISC licence), drawn inline so
    it takes the text colour and needs no extra request:

        <x-icon name="clock" />

    Decorative only: it is hidden from screen readers, so the text next to it
    must say the same thing. An icon never stands in for a label.

    Props — Blade has no typed props, so this block IS the contract.

      string $name  clock | map-pin | users | gamepad

    Styles: resources/scss/components/_icon.scss
--}}
@props(['name'])

<svg
    {{ $attributes->class(['icon']) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="2"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>
    @switch($name)
        @case('clock')
            <circle cx="12" cy="12" r="10" />
            <path d="M12 6v6l4 2" />
            @break
        @case('map-pin')
            <path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0" />
            <circle cx="12" cy="10" r="3" />
            @break
        @case('users')
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            @break
        @case('gamepad')
            <path d="M6 11h4M8 9v4M15 12h.01M18 10h.01" />
            <path d="M17.32 5H6.68a4 4 0 0 0-3.978 3.59l-.017.152C2.604 9.416 2 14.456 2 16a3 3 0 0 0 3 3c1 0 1.5-.5 2-1l1.414-1.414A2 2 0 0 1 9.828 16h4.344a2 2 0 0 1 1.414.586L17 18c.5.5 1 1 2 1a3 3 0 0 0 3-3c0-1.545-.604-6.584-.685-7.258l-.017-.151A4 4 0 0 0 17.32 5z" />
            @break
    @endswitch
</svg>
