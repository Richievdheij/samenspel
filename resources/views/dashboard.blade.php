{{--
    The signed-in start page: the events the user signed up for, and how far
    they are from organising one themselves.
--}}
<x-layouts.app :title="__('Dashboard')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Dashboard') }}</h1>
        <p class="page-header__intro">{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</p>
    </x-slot:header>

    <section class="card" aria-labelledby="my-sign-ups">
        <h2 class="card__title" id="my-sign-ups">{{ __('My sign-ups (:count)', ['count' => $upcomingSignUps->count()]) }}</h2>

        @if ($upcomingSignUps->isEmpty())
            <p class="card__intro">{{ __('You are not signed up for an upcoming event yet.') }}</p>
            <x-button variant="primary" :href="route('events.index')">{{ __('View the events') }}</x-button>
        @else
            <ul class="sign-up-list">
                @foreach ($upcomingSignUps as $event)
                    <li class="sign-up-list__item">
                        <a class="sign-up-list__title" href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                        <time class="sign-up-list__date" datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l j F, H:i') }}</time>
                        <x-status-pill :event="$event" />
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($pastSignUps->isNotEmpty())
            <h3 class="sign-up-list__heading">{{ __('Earlier sign-ups') }}</h3>
            <ul class="sign-up-list sign-up-list--past">
                @foreach ($pastSignUps as $event)
                    <li class="sign-up-list__item">
                        <a class="sign-up-list__title" href="{{ route('events.show', $event) }}">{{ $event->title }}</a>
                        <time class="sign-up-list__date" datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('j F Y') }}</time>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <section class="card event-create__dashboard-card" aria-labelledby="organise-an-event">
        <h2 class="card__title" id="organise-an-event">{{ __('Organise an event') }}</h2>
        @if ($canOrganize)
            <p class="card__intro">{{ __('You can organise events yourself now.') }}</p>
            <x-button :href="route('events.create')">{{ __('Create an event') }}</x-button>
        @else
            <p class="card__intro">{{ __('You have signed up for :count of the 3 events organised by other people that you need.', ['count' => $signUpsCount]) }}</p>
            <p>{{ trans_choice('Sign up for :count more event.|Sign up for :count more events.', $signUpsRemaining) }}</p>
            <x-button :href="route('events.index')">{{ __('Find an event') }}</x-button>
        @endif
    </section>
</x-layouts.app>
