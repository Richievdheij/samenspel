{{--
    <x-status-pill>, whether an event still takes sign-ups:

        <x-status-pill :event="$event" />

    "Open" and "Closed" come from the stored status, "Full" is derived from the
    sign-ups. A closed event that is also full shows "Closed": closing is the
    organiser's choice and says more than the count does. The label is text, so
    colour is never the only signal.

    Props — Blade has no typed props, so this block IS the contract.

      App\Models\Event $event  load it with withCount('participants'), or each
                               pill runs its own count query

    Styles: resources/scss/components/_status-pill.scss
--}}
@props(['event'])

@php
    [$modifier, $label] = match (true) {
        $event->status === \App\Enums\EventStatus::Closed => ['closed', __('Closed')],
        $event->isFull() => ['full', __('Full')],
        default => ['open', __('Open')],
    };
@endphp

<span {{ $attributes->class(['status-pill', 'status-pill--'.$modifier]) }}>{{ $label }}</span>
