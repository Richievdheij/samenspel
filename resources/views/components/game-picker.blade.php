@props(['games', 'selectedGameIds' => [], 'eventId' => null])

@php
    $selectedGames = $games->whereIn('id', $selectedGameIds);
    $gameError = $errors->first('games') ?: $errors->first('games.*');
@endphp

<span class="field__label" id="event-games-label">{{ __('Games') }}</span>
<details class="event-create__select" data-filter-select data-event-games @if ($eventId) data-event-id="{{ $eventId }}" @endif data-required-message="{{ __('Choose at least one game.') }}" data-search-empty="{{ __('No games match your search.') }}" data-all-label="{{ __('Choose games') }}" data-multiple-label="{{ __('Games selected (:count)') }}" data-remove-label="{{ __('Remove game: :name') }}" data-catalog-search-url="{{ route('events.games.search', array_filter(['event_id' => $eventId])) }}" data-game-add-url="{{ route('events.games.store') }}" data-search-hint="{{ __('Type at least 3 characters to search the catalog.') }}" data-search-invalid="{{ __('Enter a valid game title.') }}" data-select-error="{{ __('This game could not be selected. Try again.') }}">
    <summary class="field__control event-create__select-toggle" aria-labelledby="event-games-label event-games-choice" @if ($gameError) aria-invalid="true" aria-describedby="event-games-error" @endif>
        <span id="event-games-choice" data-filter-summary>
            @if ($selectedGames->count() === 1)
                {{ $selectedGames->first()->name }}
            @elseif ($selectedGames->count() > 1)
                {{ __('Games selected (:count)', ['count' => $selectedGames->count()]) }}
            @else
                {{ __('Choose games') }}
            @endif
        </span>
        <x-icon name="chevron-down" />
    </summary>
    <div class="event-create__options event-create__options--games" role="group" aria-label="{{ __('Games') }}">
        <label class="event-create__search">
            <span class="sr-only">{{ __('Search games') }}</span>
            <input class="field__control" type="search" data-option-search placeholder="{{ __('Search games') }}" autocomplete="off" maxlength="80">
        </label>
        <div class="event-create__local-results" data-local-results>
            @foreach ($games as $game)
                <label class="event-create__option" data-option-row @if ($game->provider_id) data-provider-id="{{ $game->provider_id }}" @endif>
                    <input class="checkbox__control" type="checkbox" name="games[]" value="{{ $game->id }}" @checked(in_array($game->id, $selectedGameIds, true))>
                    <span class="event-create__game-option-logo" aria-hidden="true">
                        @if ($game->cover_image_path || $game->logo_image_path || $game->cover_image_url)
                            <img src="{{ $game->cover_image_path ? Storage::disk('public')->url($game->cover_image_path) : ($game->logo_image_path ? Storage::disk('public')->url($game->logo_image_path) : $game->cover_image_url) }}" alt="" loading="lazy">
                        @else
                            {{ mb_strtoupper(mb_substr($game->name, 0, 2)) }}
                        @endif
                    </span>
                    <span data-option-name>{{ $game->name }}</span>
                </label>
            @endforeach
        </div>
        <div class="event-create__catalog" data-game-catalog>
            <p class="event-create__catalog-heading" data-game-results-heading hidden>{{ __('More from the game catalog') }}</p>
            <div data-game-results></div>
            <p class="field__hint" data-game-status aria-live="polite">{{ __('Type at least 3 characters to search the catalog.') }}</p>
        </div>
    </div>
</details>
@if ($gameError)
    <p class="field__error" id="event-games-error">{{ $gameError }}</p>
@endif
<div class="event-create__chips" role="group" aria-label="{{ __('Selected games') }}" data-event-game-chips hidden></div>
