{{--
    The upcoming events, soonest first. Open to guests, so anyone can see what
    is being played before they make an account.
--}}
<x-layouts.app :title="__('Events')">
    <x-slot:header>
        <h1>{{ __('Upcoming events') }}</h1>
    </x-slot:header>

    @if ($events->isEmpty())
        <p class="card">{{ __('There are no upcoming events yet.') }}</p>
    @else
        <ul class="event-list">
            @foreach ($events as $event)
                <li class="event-list__item card">
                    <h2 class="event-list__title">{{ $event->title }}</h2>
                    <time class="event-list__date" datetime="{{ $event->starts_at->toIso8601String() }}">
                        {{ $event->starts_at->translatedFormat('l j F Y, H:i') }}
                    </time>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
