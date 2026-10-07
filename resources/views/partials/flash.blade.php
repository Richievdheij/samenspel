{{--
    Flash messages. Two session keys only — `status` and `error` — because a
    convention with two names is followed and one with six is guessed at.

    Colour is never the only signal: each variant carries a role and a visible
    title, so the message survives a colour-blind reader and a screen reader both.

    Three statuses are not messages but keys, kept exactly as Breeze sets them so
    code and tests written against Breeze still match. The form each belongs to
    shows its own feedback next to the button that caused it ("Saved.", "A new
    verification link has been sent…"), so they are not repeated up here.
--}}
@php
    $keyedStatuses = ['profile-updated', 'password-updated', 'verification-link-sent'];
@endphp

@if (session('status') && ! in_array(session('status'), $keyedStatuses, true))
    <x-alert variant="success" :title="__('Done')" role="status">
        {{ session('status') }}
    </x-alert>
@endif

@if (session('error'))
    <x-alert variant="danger" :title="__('Something went wrong')" role="alert">
        {{ session('error') }}
    </x-alert>
@endif
