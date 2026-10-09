{{--
    <x-back-link>, a way back to the page one level up:

        <x-back-link :href="route('home')">{{ __('Back to home') }}</x-back-link>

    Always a real link to a named route, never history.back(): that also works
    without JavaScript, from a bookmark and from a link someone shared, where
    there is no history to go back to.

    Props — Blade has no typed props, so this block IS the contract.

      string $href  where "back" goes

    Styles: resources/scss/components/_back-link.scss
--}}
@props(['href'])

<a {{ $attributes->class(['back-link']) }} href="{{ $href }}">
    <x-icon name="arrow-left" class="back-link__icon" />
    {{ $slot }}
</a>
