{{--
    <x-memphis>, the loose geometric shapes of the Memphis style: a squiggle, a
    triangle, a ring, a zigzag, a cross, a dot and a patch of dots. Each variant
    places them for one spot on the site; the shapes and their colours come from
    the style tokens, so changing a shape there changes it everywhere.

        <x-memphis variant="header" />

    Decorative: hidden from screen readers and from the pointer. The parent
    needs `position: relative`, and its content a z-index above 0.

    Props — Blade has no typed props, so this block IS the contract.

      string $variant  header | hero | guest

    Styles: resources/scss/components/_memphis.scss
--}}
@props(['variant' => 'header'])

<div {{ $attributes->class(['memphis', 'memphis--'.$variant]) }} aria-hidden="true">
    <span class="memphis__shape memphis__shape--squiggle"></span>
    <span class="memphis__shape memphis__shape--triangle"></span>
    <span class="memphis__shape memphis__shape--ring"></span>
    <span class="memphis__shape memphis__shape--zigzag"></span>
    <span class="memphis__shape memphis__shape--cross"></span>
    <span class="memphis__shape memphis__shape--dot"></span>
    <span class="memphis__shape memphis__shape--dots"></span>
</div>
