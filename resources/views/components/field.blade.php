{{--
    <x-field name="title" :label="__('Title')" required />

    Wires up the four things a form field always needs and everyone forgets one
    of: a real <label for>, the old input value on a failed submit, the validation
    message, and aria-invalid/aria-describedby so a screen reader is told about
    the error rather than shown a red border it cannot see.

    Props — Blade has no typed props, so this block IS the contract. `@props`
    carries names and defaults only; the compiler never checks a type.

      string      $name   REQUIRED. The input name, the label's for, and the
                          validation key that drives aria-invalid.
      string|null $label  visible label text; falls back to $name
      string      $type   any HTML input type. Defaults to text.
      string|null $value  initial value; old($name) always wins on a failed submit
      string|null $hint   help text, wired to the input via aria-describedby
      string|null $id     the input id; falls back to $name. Needed when two
                          forms on one page share a field name
      string      $bag    the error bag to read. A form that validates with
                          validateWithBag('x') names it here, so its messages
                          never appear under a same-named field of another form

    Everything else — required, autofocus, autocomplete — lands on the <input>.

    Styles: resources/scss/components/_field.scss
--}}
@props([
    'name',
    'label' => null,
    'type' => 'text',
    'value' => null,
    'hint' => null,
    'id' => null,
    'bag' => 'default',
])

@php
    $id ??= $name;
    $fieldErrors = $errors->getBag($bag);
    $hasError = $fieldErrors->has($name);
    $describedBy = array_filter([
        $hint ? $id.'-hint' : null,
        $hasError ? $id.'-error' : null,
    ]);
@endphp

<div class="field {{ $hasError ? 'field--invalid' : '' }}">
    <label class="field__label" for="{{ $id }}">{{ $label ?? $name }}</label>

    <input
        {{ $attributes->class('field__control') }}
        id="{{ $id }}"
        name="{{ $name }}"
        type="{{ $type }}"
        value="{{ old($name, $value) }}"
        @if ($hasError) aria-invalid="true" @endif
        @if ($describedBy) aria-describedby="{{ implode(' ', $describedBy) }}" @endif
    >

    @if ($hint)
        <p class="field__hint" id="{{ $id }}-hint">{{ $hint }}</p>
    @endif

    @if ($hasError)
        <p class="field__error" id="{{ $id }}-error">{{ $fieldErrors->first($name) }}</p>
    @endif
</div>
