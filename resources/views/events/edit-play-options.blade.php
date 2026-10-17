<x-layouts.app :title="__('Edit games and platforms')" :back="route('events.show', $event)" :back-label="__('Back to event')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Edit games and platforms') }}</h1>
        <p class="page-header__intro">{{ $event->title }}</p>
    </x-slot:header>

    @php
        $selectedGameIds = is_array(old('games')) ? array_map('intval', old('games')) : $event->games->modelKeys();
        $selectedPlatformIds = is_array(old('platforms')) ? array_map('intval', old('platforms')) : $event->platforms->modelKeys();
        $selectedPlatforms = $platforms->whereIn('id', $selectedPlatformIds);
    @endphp

    <form class="card event-create" method="POST" action="{{ route('events.play-options.update', $event) }}" data-event-create>
        @csrf
        @method('PUT')
        <div class="event-create__grid">
            <div class="field">
                <x-game-picker :games="$games" :selected-game-ids="$selectedGameIds" :event-id="$event->id" />
            </div>
            <div class="field">
                <span class="field__label" id="event-platforms-label">{{ __('Platforms') }}</span>
                <details class="event-create__select" data-filter-select data-event-platforms data-required-message="{{ __('Choose at least one platform.') }}" data-remove-label="{{ __('Remove platform: :name') }}" data-all-label="{{ __('Choose platforms') }}" data-multiple-label="{{ __('Platforms selected (:count)') }}">
                    <summary class="field__control event-create__select-toggle" aria-labelledby="event-platforms-label event-platforms-choice" @if ($errors->has('platforms') || $errors->has('platforms.*')) aria-invalid="true" aria-describedby="event-platforms-error" @endif>
                        <span id="event-platforms-choice" data-filter-summary>{{ $selectedPlatforms->count() === 1 ? __($selectedPlatforms->first()->name) : ($selectedPlatforms->count() > 1 ? __('Platforms selected (:count)', ['count' => $selectedPlatforms->count()]) : __('Choose platforms')) }}</span>
                        <x-icon name="chevron-down" />
                    </summary>
                    <div class="event-create__options" role="group" aria-label="{{ __('Platforms') }}">
                        @foreach ($platforms as $platform)
                            <label class="event-create__option"><input class="checkbox__control" type="checkbox" name="platforms[]" value="{{ $platform->id }}" @checked(in_array($platform->id, $selectedPlatformIds, true))><span class="event-create__platform-name"><x-platform-icon :slug="$platform->slug" />{{ __($platform->name) }}</span></label>
                        @endforeach
                    </div>
                </details>
                @if ($errors->has('platforms') || $errors->has('platforms.*')) <p class="field__error" id="event-platforms-error">{{ $errors->first('platforms') ?: $errors->first('platforms.*') }}</p> @endif
                <div class="event-create__chips" role="group" aria-label="{{ __('Selected platforms') }}" data-event-platform-chips hidden></div>
            </div>
        </div>
        <template data-event-chip-template>
            <button class="event-search__chip event-create__chip" type="button"><span class="event-create__chip-logo" data-chip-logo aria-hidden="true" hidden></span><span data-chip-name></span><x-icon name="x" /></button>
        </template>
        <div class="event-create__actions"><x-button variant="primary" type="submit">{{ __('Save games and platforms') }}</x-button></div>
    </form>
</x-layouts.app>
