{{--
    <x-event-status-toggle>, the organiser's button that opens or closes sign-ups:

        @can('toggleStatus', $event)
            <x-event-status-toggle :event="$event" />
        @endcan

    A small POST form that sends the status it switches to. The visible text says
    what the button does; the accessible name adds the title, because a list holds
    several of these buttons and "Close sign-ups" alone does not say for which.

    Props — Blade has no typed props, so this block IS the contract.

      App\Models\Event $event  the event to switch

    Styles: resources/scss/components/_event-card.scss (.event-status-toggle)
--}}
@props(['event'])

@php
    $isClosed = $event->status === \App\Enums\EventStatus::Closed;
@endphp

<form {{ $attributes->class('event-status-toggle') }} method="POST" action="{{ route('events.toggle-status', $event) }}">
    @csrf
    <input type="hidden" name="status" value="{{ $isClosed ? \App\Enums\EventStatus::Open->value : \App\Enums\EventStatus::Closed->value }}">
    <x-button type="submit" :variant="$isClosed ? 'primary' : null" :aria-label="$isClosed ? __('Reopen sign-ups for :title', ['title' => $event->title]) : __('Close sign-ups for :title', ['title' => $event->title])">
        {{ $isClosed ? __('Reopen sign-ups') : __('Close sign-ups') }}
    </x-button>
</form>
