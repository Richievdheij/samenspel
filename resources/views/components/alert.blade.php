{{--
    <x-alert variant="success" :title="__('Done')">Message</x-alert>

    Three columns: an icon for the kind of message, the title and text, and the
    close button in the top-right corner. The icon repeats what the title says,
    so it is hidden from screen readers.

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

@php
    $icon = match ($variant) {
        'success' => 'check',
        'danger' => 'alert',
        default => 'info',
    };
@endphp

<div {{ $attributes->class(['alert', $variant ? 'alert--'.$variant : null, $dismissible ? 'alert--dismissible' : null]) }}>
    <x-icon :name="$icon" class="alert__icon" />

    <div class="alert__content">
        @if ($title)
            <p class="alert__title">{{ $title }}</p>
        @endif

        <div class="alert__body">{{ $slot }}</div>
    </div>

    @if ($dismissible)
        <button class="alert__close" type="button" data-dismiss="alert">
            <span class="sr-only">{{ __('Dismiss') }}</span>
            <x-icon name="x" class="alert__close-icon" />
        </button>
    @endif
</div>
