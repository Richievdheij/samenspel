{{--
    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()">

    A native <dialog>, opened as a modal by any button that names it:

        <x-button command="show-modal" commandfor="confirm-user-deletion">…</x-button>
        <x-button command="close" commandfor="confirm-user-deletion">…</x-button>

    `command`/`commandfor` are HTML invoker commands, so opening and closing take
    no JavaScript in a current browser. The browser also supplies what Breeze's
    Alpine modal wrote by hand: the focus trap, Escape to close, the inert page
    behind it and focus returning to the button that opened it.
    resources/js/app.js covers a browser without invoker commands and a click on
    the backdrop.

    Props — Blade has no typed props, so this block IS the contract.

      string $name   REQUIRED. The dialog id, which commandfor points at.
      bool   $show   open on page load — after a failed submit from inside the
                     dialog, so the error is shown where the field is. Rendered
                     as `open`, so it is visible without JavaScript too; app.js
                     then reopens it as a true modal.

    Styles: resources/scss/components/_modal.scss
--}}
@props([
    'name',
    'show' => false,
])

<dialog
    {{ $attributes->class('modal') }}
    id="{{ $name }}"
    closedby="any"
    data-modal
    @if ($show) open data-modal-show @endif
>
    <div class="modal__body">
        {{ $slot }}
    </div>
</dialog>
