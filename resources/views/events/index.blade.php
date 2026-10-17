{{--
    The upcoming events, soonest first, ten to a page. Open to guests, so anyone
    can see what is being played before they make an account.

    Each card reads in the order a player decides: which game, what the event is
    called, when, and whether there is still room. The entire card
    opens the detail page.
--}}
<x-layouts.app :title="__('Events')" :description="__('Discover upcoming gaming events on Samenspel. Sign in for locations and participant details.')" :canonical="route('events.index')" :robots="request()->query() === [] ? null : 'noindex, follow'">
    <x-slot:header>
        <h1>{{ __('Upcoming events') }}</h1>
        <p class="page-header__intro">
            @if ($search !== '')
                {{ __('Search results for ":term" (:count)', ['term' => $search, 'count' => $events->total()]) }}
            @else
                {{ trans_choice('One event is coming up.|:count events are coming up.', $events->total()) }}
            @endif
        </p>
    </x-slot:header>

    <form class="event-search" method="get" action="{{ route('events.index') }}" role="search">
        <div class="event-search__row">
            <div class="event-search__field {{ $search !== '' ? 'event-search__field--has-reset' : '' }}">
                <x-field
                    name="q"
                    :label="__('Search events')"
                    :value="$search"
                    type="search"
                    :placeholder="__('Search by title')"
                    enterkeyhint="search"
                    data-search-input
                />
                <a class="event-search__reset" href="{{ route('events.index', array_filter(['game' => $gameId, 'category' => $categoryIds])) }}" aria-label="{{ __('Clear search') }}" data-search-reset @if ($search === '') hidden @endif>
                    <x-icon name="x" />
                </a>
            </div>
            <button class="button event-search__filter" type="button" aria-label="{{ __('Filters') }}" aria-controls="event-filters-panel" aria-expanded="false" data-filter-toggle hidden>
                <x-icon name="sliders-horizontal" class="button__icon" />
                <span class="event-search__filter-label">{{ __('Filters') }}</span>
            </button>
            <x-button class="event-search__submit" variant="primary" type="submit" :aria-label="__('Search')">
                <x-icon name="search" class="button__icon" />
                <span class="event-search__submit-label">{{ __('Search') }}</span>
            </x-button>
        </div>
        @if ($selectedGame || $selectedCategories->isNotEmpty())
            <div class="event-search__active" role="group" aria-label="{{ __('Active filters') }}">
                @if ($selectedGame)
                    <a class="event-search__chip" href="{{ route('events.index', array_filter(['q' => $search, 'category' => $categoryIds])) }}" aria-label="{{ __('Remove game filter: :name', ['name' => $selectedGame->name]) }}">
                        <span class="event-search__chip-name">{{ $selectedGame->name }}</span>
                        <x-icon name="x" />
                    </a>
                @endif
                @foreach ($selectedCategories as $selectedCategory)
                    <a class="event-search__chip" href="{{ route('events.index', array_filter(['q' => $search, 'game' => $gameId, 'category' => array_values(array_diff($categoryIds, [$selectedCategory->id]))])) }}" aria-label="{{ __('Remove category filter: :name', ['name' => $selectedCategory->name]) }}">
                        <span class="event-search__chip-name">{{ $selectedCategory->name }}</span>
                        <x-icon name="x" />
                    </a>
                @endforeach
            </div>
        @endif
        <div class="event-filters" id="event-filters-panel" data-filter-panel>
            <div class="event-filters__form">
                <div class="field event-filters__field">
                    <span class="field__label" id="game-label">{{ __('Game') }}</span>
                    <details class="event-filters__dropdown" data-filter-select data-single-select>
                        <summary class="field__control event-filters__dropdown-toggle" aria-labelledby="game-label game-choice">
                            <span id="game-choice" data-filter-summary>{{ $selectedGame?->name ?? __('All games') }}</span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="event-filters__options" role="group" aria-label="{{ __('Game') }}">
                            <label class="event-filters__option">
                                <input class="checkbox__control" type="radio" name="game" value="" @checked(!$selectedGame)>
                                <span>{{ __('All games') }}</span>
                            </label>
                            @foreach ($games as $game)
                                <label class="event-filters__option">
                                    <input class="checkbox__control" type="radio" name="game" value="{{ $game->id }}" @checked($gameId === $game->id)>
                                    <span>{{ $game->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                </div>
                <div class="field event-filters__field">
                    <span class="field__label" id="category-label">{{ __('Category') }}</span>
                    <details class="event-filters__dropdown" data-filter-select data-all-label="{{ __('All categories') }}" data-multiple-label="{{ __('Categories selected') }}">
                        <summary class="field__control event-filters__dropdown-toggle" aria-labelledby="category-label category-choice">
                            <span id="category-choice" data-filter-summary>
                                @if ($selectedCategories->count() === 1)
                                    {{ $selectedCategories->first()->name }}
                                @elseif ($categoryIds !== [])
                                    {{ __('Categories selected (:count)', ['count' => count($categoryIds)]) }}
                                @else
                                    {{ __('All categories') }}
                                @endif
                            </span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="event-filters__options" role="group" aria-label="{{ __('Category') }}">
                            @foreach ($categories as $category)
                                <label class="event-filters__option">
                                    <input class="checkbox__control" type="checkbox" name="category[]" value="{{ $category->id }}" @checked(in_array($category->id, $categoryIds, true))>
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                </div>
                <div class="event-filters__actions">
                    <x-button variant="primary" type="submit">{{ __('Apply filters') }}</x-button>
                </div>
            </div>
        </div>
    </form>

    @if ($events->isEmpty())
        <div class="card event-empty">
            <p>
                @if ($search !== '' && ($gameId || $categoryIds !== []))
                    {{ __('No events match your search and filters.') }}
                @elseif ($search !== '')
                    {{ __('No events match your search.') }}
                @elseif ($gameId || $categoryIds !== [])
                    {{ __('No events match your filters.') }}
                @else
                    {{ __('There are no upcoming events yet.') }}
                @endif
            </p>
            @if ($gameId || $categoryIds !== [])
                <a class="event-empty__clear" href="{{ route('events.index', array_filter(['q' => $search])) }}">{{ __('Clear filters') }}</a>
            @endif
        </div>
    @else
        <ul class="event-list">
            @foreach ($events as $event)
                <li>
                    <a class="event-card {{ $event->games->count() === 1 ? 'event-card--featured' : 'event-card--multiple' }}" href="{{ route('events.show', $event) }}" aria-labelledby="event-title-{{ $event->id }}">
                        <div class="event-card__visual">
                            <div class="event-card__game-feature">
                                @foreach ($event->games as $game)
                                    <div class="event-card__game-tile">
                                        @if ($game->cover_image_path)
                                            <img class="event-card__game-cover" src="{{ Storage::disk('public')->url($game->cover_image_path) }}" alt="" loading="lazy" decoding="async">
                                        @elseif ($game->cover_image_url)
                                            <img class="event-card__game-cover" src="{{ $game->cover_image_url }}" alt="" loading="lazy" decoding="async">
                                        @elseif ($game->logo_image_path)
                                            <img class="event-card__game-cover event-card__game-cover--logo" src="{{ Storage::disk('public')->url($game->logo_image_path) }}" alt="" loading="lazy" decoding="async">
                                        @else
                                            <x-icon name="gamepad" class="event-card__game-placeholder" />
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                            <time class="event-card__stamp" datetime="{{ $event->starts_at->toIso8601String() }}">
                                <span class="event-card__weekday">{{ $event->starts_at->translatedFormat('D') }}</span>
                                <span class="event-card__day">{{ $event->starts_at->translatedFormat('j') }}</span>
                                <span class="event-card__month">{{ $event->starts_at->translatedFormat('M') }}</span>
                            </time>
                            <div class="event-card__visual-status"><x-status-pill :event="$event" /></div>
                            @if ($event->games->count() === 1)
                                <span class="event-card__game-feature-name">{{ $event->games->first()->name }}</span>
                            @endif
                        </div>

                        <div class="event-card__body">
                            <div class="event-card__categories" aria-label="{{ __('Categories') }}">
                                @foreach ($event->categories as $category)
                                    <x-category-label :name="$category->name" />
                                @endforeach
                            </div>
                            @if ($event->platforms->isNotEmpty())
                                <div class="event-card__categories" aria-label="{{ __('Platforms') }}">
                                    @foreach ($event->platforms as $platform)
                                        <x-platform-label :platform="$platform" />
                                    @endforeach
                                </div>
                            @endif
                            <h2 class="event-card__title" id="event-title-{{ $event->id }}">{{ $event->title }}</h2>
                            @if ($event->games->count() > 1)
                                <div class="event-card__game-names" aria-label="{{ __('Games') }}">
                                    @foreach ($event->games->take(4) as $game)
                                        <x-game-badge :game="$game" compact />
                                    @endforeach
                                    @if ($event->games->count() > 4)
                                        <span class="event-card__games-note" aria-label="{{ trans_choice(':count more game|:count more games', $event->games->count() - 4) }}" title="{{ trans_choice(':count more game|:count more games', $event->games->count() - 4) }}">+{{ $event->games->count() - 4 }}</span>
                                    @endif
                                </div>
                            @endif
                            <ul class="event-card__facts">
                                <li class="event-card__fact">
                                    <x-icon name="clock" />
                                    {{ $event->starts_at->translatedFormat('H:i') }}
                                    @if ($event->durationInMinutes() !== null)
                                        – {{ $event->ends_at->isSameDay($event->starts_at) ? $event->ends_at->translatedFormat('H:i') : $event->ends_at->translatedFormat('j M H:i') }}
                                    @endif
                                </li>
                                @auth
                                    <li class="event-card__fact">
                                        <x-icon name="map-pin" />
                                        {{ $event->is_online ? __('Online') : ($event->location ?? __('Location not announced yet')) }}
                                    </li>
                                @endauth
                            </ul>
                            <div class="event-card__footer">
                                <span class="event-card__count">
                                    <x-icon name="users" />
                                    @if ($event->max_participants === null)
                                        {{ trans_choice(':count player · no limit|:count players · no limit', $event->participants_count) }}
                                    @else
                                        {{ __(':count / :max players', ['count' => $event->participants_count, 'max' => $event->max_participants]) }}
                                    @endif
                                </span>
                                <span class="event-card__action">{{ __('View event') }} <x-icon name="arrow-right" /></span>
                            </div>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>

        {{ $events->links() }}
    @endif
</x-layouts.app>
