{{--
    <x-alert variant="success" :title="__('Done')">Message</x-alert>

    `role` is passed through by the caller rather than derived from the variant,
    because the two are different questions: `success`/`danger` is what it looks
    like, `status`/`alert` is how urgently a screen reader should interrupt.

    Props — Blade has no typed props, so this block IS the contract. `@props`
    carries names and defaults only; the compiler never checks a type.

      string|null $variant      success | danger. null renders the base block.
      string|null $title        bold first line; omit for a body-only message
      bool        $dismissible  renders the close button and its sr-only label

    Styles: resources/scss/components/_alert.scss
--}}
@props([
    'variant' => null,
    'title' => null,
    'dismissible' => true,
])

<div {{ $attributes->class(['alert', $variant ? 'alert--'.$variant : null]) }}>
    @if ($title)
        <p class="alert__title">{{ $title }}</p>
    @endif

    <div class="alert__body">{{ $slot }}</div>

    @if ($dismissible)
        <button class="button button--ghost" type="button" data-dismiss="alert">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <span aria-hidden="true">&times;</span>
        </button>
    @endif
</div>
