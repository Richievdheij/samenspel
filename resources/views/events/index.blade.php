{{--
    The upcoming events, soonest first, ten to a page. Open to guests, so anyone
    can see what is being played before they make an account.

    Each card reads in the order a player decides: which game, what the event is
    called, when and where, and whether there is still room. The card itself is
    not clickable, so it has no hover state; only what acts does.
--}}
<x-layouts.app :title="__('Events')">
    <x-slot:header>
        <h1>{{ __('Upcoming events') }}</h1>
        <p class="page-header__intro">
            {{ trans_choice('One event is coming up.|:count events are coming up.', $events->total()) }}
        </p>
    </x-slot:header>

    @if ($events->isEmpty())
        <p class="card">{{ __('There are no upcoming events yet.') }}</p>
    @else
        <ul class="event-list">
            @foreach ($events as $event)
                <li class="event-card">
                    <time class="event-card__stamp" datetime="{{ $event->starts_at->toIso8601String() }}">
                        <span class="event-card__weekday">{{ $event->starts_at->translatedFormat('D') }}</span>
                        <span class="event-card__day">{{ $event->starts_at->translatedFormat('j') }}</span>
                        <span class="event-card__month">{{ $event->starts_at->translatedFormat('M') }}</span>
                    </time>

                    <div class="event-card__body">
                        <p class="event-card__game">
                            <x-icon name="gamepad" />
                            {{ $event->game->name }}
                            <span class="event-card__category">{{ $event->category->name }}</span>
                        </p>

                        <h2 class="event-card__title">{{ $event->title }}</h2>

                        <ul class="event-card__facts">
                            <li class="event-card__fact">
                                <x-icon name="clock" />
                                {{ $event->starts_at->translatedFormat('H:i') }}
                            </li>
                            <li class="event-card__fact">
                                <x-icon name="map-pin" />
                                {{ $event->location }}
                            </li>
                        </ul>
                    </div>

                    <div class="event-card__aside">
                        <x-status-pill :event="$event" />

                        <p class="event-card__count">
                            <x-icon name="users" />
                            {{ __(':count / :max players', ['count' => $event->participants_count, 'max' => $event->max_participants]) }}
                        </p>
                    </div>
                </li>
            @endforeach
        </ul>

        {{ $events->links() }}
    @endif
</x-layouts.app>
