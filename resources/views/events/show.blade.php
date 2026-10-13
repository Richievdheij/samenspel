<x-layouts.app :title="$event->title" :back="route('events.index')" :back-label="__('Back to events')">
    <x-slot:header>
        <h1>{{ $event->title }}</h1>
        <div class="event-detail__classification">
            <p class="event-detail__game"><x-icon name="gamepad" /> {{ $event->game->name }}</p>
            <x-category-label :name="$event->category->name" />
        </div>
    </x-slot:header>

    <div class="event-detail">
        <section class="card event-detail__description" aria-labelledby="event-description">
            <h2 class="card__title" id="event-description">{{ __('About this event') }}</h2>
            <p class="event-detail__text">{{ $event->description }}</p>
        </section>

        <aside class="card event-detail__summary" aria-label="{{ __('Event information') }}">
            <x-status-pill :event="$event" />

            <dl class="event-detail__facts">
                <div>
                    <dt>{{ __('Date and time') }}</dt>
                    <dd><time datetime="{{ $event->starts_at->toIso8601String() }}">{{ $event->starts_at->translatedFormat('l j F Y, H:i') }}</time></dd>
                </div>
                <div>
                    <dt>{{ __('Location') }}</dt>
                    <dd>{{ $event->location }}</dd>
                </div>
                <div>
                    <dt>{{ __('Organiser') }}</dt>
                    <dd>{{ $event->organizer->name }}</dd>
                </div>
                <div>
                    <dt>{{ __('Available places') }}</dt>
                    <dd class="event-detail__availability">
                        <span>{{ max(0, $event->max_participants - $event->participants_count) }} / {{ $event->max_participants }}</span>
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
                    </dd>
                </div>
            </dl>
        </aside>

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
    </div>
</x-layouts.app>
