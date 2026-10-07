{{--
    <x-button>, an anonymous component: markup only, no PHP class needed.

        <x-button variant="primary" type="submit">Save</x-button>
        <x-button :href="route('examples.index')">Back</x-button>

    An `href` renders an <a>, anything else a <button>. That is the accessibility
    rule, not a styling preference: a link navigates and a button acts, and
    swapping them breaks keyboard and screen-reader behaviour even when the two
    look identical.

    Props — Blade has no typed props, so this block IS the contract. `@props`
    carries names and defaults only; the compiler never checks a type. Keep it
    accurate by hand, and keep it next to the @props it describes.

      string|null $variant  primary | ghost | danger | link | block. null renders
                            the base block.
      string|null $href     present renders <a>, absent renders <button>
      string      $type     button | submit | reset. Ignored when $href is set.

    Styles: resources/scss/components/_button.scss
--}}
@props([
    'variant' => null,
    'href' => null,
    'type' => 'button',
])

@php
    $classes = ['button', $variant ? 'button--'.$variant : null];
@endphp

@if ($href)
    <a {{ $attributes->class($classes) }} href="{{ $href }}">
        {{ $slot }}
    </a>
@else
    <button {{ $attributes->class($classes) }} type="{{ $type }}">
        {{ $slot }}
    </button>
@endif
