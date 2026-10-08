{{--
    <x-logo>, the Samenspel logo as an <img> from public/brand/.

        <x-logo />
        <x-logo layout="stacked" variant="dark" />

    Pick the variant by the background the logo sits on, not by the page: light
    on paper and surface, dark on felt. The alt text is the app name, so a link
    wrapped around the logo is announced as "Samenspel".

    The width and height are the SVG's own viewBox, so the browser reserves the
    space before the file arrives. The rendered size comes from the stylesheet.

    Props — Blade has no typed props, so this block IS the contract.

      string $layout   horizontal | stacked | emblem | wordmark
      string $variant  light | dark, or a one-colour mono-felt | mono-paper |
                       mono-black | mono-white

    Every file and when to use it: docs/design-system.md, section "Logo".
    Styles: resources/scss/components/_logo.scss
--}}
@props([
    'layout' => 'horizontal',
    'variant' => 'light',
])

@php
    [$width, $height] = match ($layout) {
        'stacked' => [384, 194],
        'emblem' => [80, 100],
        'wordmark' => [384, 66],
        default => [492, 100],
    };
@endphp

<img
    {{ $attributes->class(['logo', 'logo--'.$layout]) }}
    src="{{ asset('brand/samenspel-'.$layout.'-'.$variant.'.svg') }}"
    alt="{{ config('app.name') }}"
    width="{{ $width }}"
    height="{{ $height }}"
>
