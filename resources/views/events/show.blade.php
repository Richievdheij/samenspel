<x-layouts.app :title="$event->title" :description="__('Discover :game events on Samenspel. Sign in for locations and participant details.', ['game' => $event->games->pluck('name')->implode(', ')])" :canonical="route('events.show', $event)" :back="route('events.index')" :back-label="__('Back to events')">
    <x-slot:header>
        <h1>{{ $event->title }}</h1>
        <div class="event-detail__classification">
            @if ($event->games->count() > 3)
                <details class="event-detail__games">
                    <summary class="event-detail__games-toggle">{{ __('Games') }} ({{ $event->games->count() }}) <x-icon name="chevron-down" /></summary>
                    <div class="event-detail__games-list">
                        @foreach ($event->games as $game)
                            <x-game-badge :game="$game" />
                        @endforeach
                    </div>
                </details>
            @else
                @foreach ($event->games as $game)
                    <x-game-badge :game="$game" />
                @endforeach
            @endif
            @foreach ($event->categories as $category)
                <x-category-label :name="$category->name" />
            @endforeach
            @foreach ($event->platforms as $platform)
                <x-platform-label :platform="$platform" />
            @endforeach
        </div>
    </x-slot:header>

    <div class="event-detail">
        @auth
            <section class="card event-detail__description" aria-labelledby="event-description">
                <h2 class="card__title" id="event-description">{{ __('About this event') }}</h2>
                <p class="event-detail__text">{{ $event->description }}</p>
                @canany(['update', 'toggleStatus', 'delete'], $event)
                    <div class="event-detail__actions">
                        @can('update', $event)
                            <x-button :href="route('events.edit', $event)">{{ __('Edit event') }}</x-button>
                        @endcan
                        @can('toggleStatus', $event)
                            <x-event-status-toggle :event="$event" />
                        @endcan
                        @can('delete', $event)
                            <x-button variant="danger" command="show-modal" commandfor="confirm-event-deletion">{{ __('Delete event') }}</x-button>
                        @endcan
                    </div>
                @endcanany
                @can('delete', $event)
                    <x-modal name="confirm-event-deletion" aria-labelledby="confirm-event-deletion-title">
                        <form class="form" method="POST" action="{{ route('events.destroy', $event) }}">
                            @csrf
                            @method('DELETE')

                            <h2 class="modal__title" id="confirm-event-deletion-title">{{ __('Delete this event?') }}</h2>
                            <p class="modal__text">{{ trans_choice('This permanently deletes the event, together with :count sign-up.|This permanently deletes the event, together with :count sign-ups.', $event->participants_count) }}</p>

                            <div class="form__actions form__actions--end">
                                <x-button command="close" commandfor="confirm-event-deletion">{{ __('Cancel') }}</x-button>
                                <x-button variant="danger" type="submit">{{ __('Delete event') }}</x-button>
                            </div>
                        </form>
                    </x-modal>
                @endcan
            </section>
        @else
            <section class="card event-detail__description" aria-labelledby="event-description">
                <h2 class="card__title" id="event-description">{{ __('Event details are private') }}</h2>
                <p>{{ __('Sign in to see the description, location, organiser and participants. This protects the privacy of everyone attending.') }}</p>
                <div class="event-detail__actions">
                    <x-button variant="primary" :href="route('login')">{{ __('Log in') }}</x-button>
                    <x-button :href="route('register')">{{ __('Create an account') }}</x-button>
                </div>
            </section>
        @endauth

        <aside class="card event-detail__summary" aria-label="{{ __('Event information') }}">
            <x-status-pill :event="$event" />

            <dl class="event-detail__facts">
                <div>
                    <dt>{{ __('Starts') }}</dt>
                    <dd><time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l j F Y, H:i') }}</time></dd>
                </div>
                <div>
                    <dt>{{ __('Ends') }}</dt>
                    <dd>
                        @if ($event->durationInMinutes() !== null)
                            <time datetime="{{ $event->ends_at->toIso8601String() }}">{{ $event->ends_at->translatedFormat('l j F Y, H:i') }}</time>
                        @else
                            {{ __('End time not announced yet') }}
                        @endif
                    </dd>
                </div>
                @if ($event->durationInMinutes() !== null)
                    <div>
                        <dt>{{ __('Duration') }}</dt>
                        <dd>{{ __(':hours h :minutes min', ['hours' => intdiv($event->durationInMinutes(), 60), 'minutes' => $event->durationInMinutes() % 60]) }}</dd>
                    </div>
                @endif
                <div>
                    <dt>{{ __('Time zone') }}</dt>
                    <dd>{{ __('Amsterdam time (:offset)', ['offset' => $event->utcOffsetLabel()]) }}</dd>
                </div>
                @auth
                    <div>
                        <dt>{{ __('Location') }}</dt>
                        <dd>{{ $event->is_online ? __('Online') : ($event->location ?? __('Location not announced yet')) }}</dd>
                    </div>
                    <div>
                        <dt>{{ __('Organiser') }}</dt>
                        <dd>{{ $event->organizer->name }}</dd>
                    </div>
                @endauth
                @if ($event->min_participants !== null)
                    <div>
                        <dt>{{ __('Minimum participants') }}</dt>
                        <dd>{{ $event->min_participants }}</dd>
                    </div>
                @endif
                <div>
                    <dt>{{ $event->max_participants === null ? __('Participants') : __('Available places') }}</dt>
                    <dd class="event-detail__availability">
                        <span>
                            @if ($event->max_participants === null)
                                {{ trans_choice(':count player · no limit|:count players · no limit', $event->participants_count) }}
                            @else
                                {{ max(0, $event->max_participants - $event->participants_count) }} / {{ $event->max_participants }}
                            @endif
                        </span>
                        @auth
                            @if ($event->participants_count > 0)
                                <a
                                    class="event-detail__preview"
                                    href="#event-participants"
                                    aria-label="{{ __('View participants (:count)', ['count' => $event->participants_count]) }}"
                                >
                                    @foreach ($participantPreview as $participant)
                                        <span class="avatar event-detail__preview-avatar" title="{{ $participant->name }}" aria-hidden="true">{{ $participant->initials() }}</span>
                                    @endforeach
                                    @if ($event->participants_count > 4)
                                        <span class="event-detail__preview-more" aria-hidden="true">+{{ $event->participants_count - 4 }}</span>
                                    @endif
                                </a>
                            @endif
                        @endauth
                    </dd>
                </div>
            </dl>

            @auth
                <div class="event-detail__sign-up">
                    @if ($isSignedUp)
                        <p class="event-detail__sign-up-state"><x-icon name="check" /> {{ __('You are signed up for this event.') }}</p>
                    @elseif ($signUpRefusal === null)
                        <form method="POST" action="{{ route('events.sign-up.store', $event) }}">
                            @csrf
                            <x-button variant="primary" type="submit">{{ __('Sign up') }}</x-button>
                        </form>
                    @else
                        <p class="event-detail__sign-up-note">{{ $signUpRefusal }}</p>
                    @endif
                    @error('sign_up')
                        <p class="field__error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
            @endauth
        </aside>

        @auth
            <section class="card event-detail__participants" aria-labelledby="event-participants" data-participants>
                <h2 class="card__title" id="event-participants" tabindex="-1" data-participants-heading>{{ __('Participants (:count)', ['count' => $event->participants_count]) }}</h2>
                @if ($participants->total() === 0)
                    <p>{{ __('No participants have signed up yet.') }}</p>
                @else
                    <ul class="event-detail__people">
                        @foreach ($participants as $participant)
                            <li class="event-detail__person">
                                <span class="avatar" aria-hidden="true">{{ $participant->initials() }}</span>
                                <span>{{ $participant->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <div class="event-detail__pagination" data-participant-pages>
                        {{ $participants->links() }}
                    </div>
                @endif
            </section>
        @endauth
    </div>
</x-layouts.app>
