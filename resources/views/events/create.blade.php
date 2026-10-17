<x-layouts.app :title="__('Create an event')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Create an event') }}</h1>
        <p class="page-header__intro">{{ __('Bring players together for a game you enjoy.') }}</p>
    </x-slot:header>

    @if (! $canOrganize)
        <section class="card event-create__eligibility" aria-labelledby="event-create-eligibility">
            <h2 class="card__title" id="event-create-eligibility">{{ __('Join three events before organising') }}</h2>
            <p>{{ __('You have signed up for :count of the 3 events organised by other people that you need.', ['count' => $signUpsCount]) }}</p>
            <p>{{ trans_choice('Sign up for :count more event.|Sign up for :count more events.', $signUpsRemaining) }}</p>
            <div class="event-create__eligibility-action">
                <x-button variant="primary" :href="route('events.index')">{{ __('Find an event') }}</x-button>
            </div>
        </section>
    @else
        @php
            $selectedGameIds = is_array(old('games')) ? array_map('intval', old('games')) : [];
            $selectedCategoryIds = is_array(old('categories')) ? array_map('intval', old('categories')) : [];
            $selectedCategories = $categories->whereIn('id', $selectedCategoryIds);
            $selectedPlatformIds = is_array(old('platforms')) ? array_map('intval', old('platforms')) : [];
            $selectedPlatforms = $platforms->whereIn('id', $selectedPlatformIds);
            $capacityMode = old('capacity_mode', 'limited');
            $startValue = old('starts_at', now()->addWeek()->format('Y-m-d\TH:i'));
            $categoryError = $errors->first('categories') ?: $errors->first('categories.*');
            $locationMode = old('location_mode') === 'online' ? 'online' : 'search';
        @endphp

        <form class="card event-create" method="POST" action="{{ route('events.store') }}" data-event-create data-schedule-required="{{ __('Choose a valid start date and time.') }}">
            @csrf
            <nav class="event-create__steps" aria-label="{{ __('Event setup') }}" data-event-steps hidden>
                <button type="button" data-step-tab="0">1. {{ __('Games and platforms') }}</button>
                <button type="button" data-step-tab="1">2. {{ __('Event details') }}</button>
                <button type="button" data-step-tab="2">3. {{ __('When and where') }}</button>
            </nav>
            <section class="event-create__step" data-event-step="0" aria-labelledby="event-step-games">
                <h2 id="event-step-games">{{ __('Games and platforms') }}</h2>
                <p class="field__hint">{{ __('Choose the games, categories and platforms for your event.') }}</p>
            <div class="event-create__grid">
                <div class="field">
                    <x-game-picker :games="$games" :selected-game-ids="$selectedGameIds" />
                </div>

                <div class="field">
                    <span class="field__label" id="event-categories-label">{{ __('Categories') }}</span>
                    <details class="event-create__select" data-filter-select data-all-label="{{ __('Choose categories') }}" data-multiple-label="{{ __('Categories selected (:count)') }}" data-event-categories data-required-message="{{ __('Choose at least one category.') }}" data-remove-label="{{ __('Remove category: :name') }}">
                        <summary class="field__control event-create__select-toggle" aria-labelledby="event-categories-label event-categories-choice" @if ($categoryError) aria-invalid="true" aria-describedby="event-categories-error" @endif>
                            <span id="event-categories-choice" data-filter-summary>
                                @if ($selectedCategories->count() === 1)
                                    {{ $selectedCategories->first()->name }}
                                @elseif ($selectedCategories->count() > 1)
                                    {{ __('Categories selected (:count)', ['count' => $selectedCategories->count()]) }}
                                @else
                                    {{ __('Choose categories') }}
                                @endif
                            </span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="event-create__options" role="group" aria-label="{{ __('Categories') }}">
                            @foreach ($categories as $category)
                                <label class="event-create__option">
                                    <input class="checkbox__control" type="checkbox" name="categories[]" value="{{ $category->id }}" @checked(in_array($category->id, $selectedCategoryIds, true))>
                                    <span>{{ $category->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                    @if ($categoryError)
                        <p class="field__error" id="event-categories-error">{{ $categoryError }}</p>
                    @endif
                    <div class="event-create__chips" role="group" aria-label="{{ __('Selected categories') }}" data-event-category-chips hidden></div>
                    <template data-event-chip-template>
                        <button class="event-search__chip event-create__chip" type="button">
                            <span class="event-create__chip-logo" data-chip-logo aria-hidden="true" hidden></span>
                            <span data-chip-name></span>
                            <x-icon name="x" />
                        </button>
                    </template>
                </div>

                <div class="field">
                    <span class="field__label" id="event-platforms-label">{{ __('Platforms') }}</span>
                    <details class="event-create__select" data-filter-select data-event-platforms data-required-message="{{ __('Choose at least one platform.') }}" data-all-label="{{ __('Choose platforms') }}" data-multiple-label="{{ __('Platforms selected (:count)') }}" data-remove-label="{{ __('Remove platform: :name') }}">
                        <summary class="field__control event-create__select-toggle" aria-labelledby="event-platforms-label event-platforms-choice" @if ($errors->has('platforms') || $errors->has('platforms.*')) aria-invalid="true" aria-describedby="event-platforms-error" @endif>
                            <span id="event-platforms-choice" data-filter-summary>
                                @if ($selectedPlatforms->count() === 1)
                                    {{ __($selectedPlatforms->first()->name) }}
                                @elseif ($selectedPlatforms->count() > 1)
                                    {{ __('Platforms selected (:count)', ['count' => $selectedPlatforms->count()]) }}
                                @else
                                    {{ __('Choose platforms') }}
                                @endif
                            </span>
                            <x-icon name="chevron-down" />
                        </summary>
                        <div class="event-create__options" role="group" aria-label="{{ __('Platforms') }}">
                            @foreach ($platforms as $platform)
                                <label class="event-create__option">
                                    <input class="checkbox__control" type="checkbox" name="platforms[]" value="{{ $platform->id }}" @checked(in_array($platform->id, $selectedPlatformIds, true))>
                                    <span class="event-create__platform-name"><x-platform-icon :slug="$platform->slug" />{{ __($platform->name) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </details>
                    @if ($errors->has('platforms') || $errors->has('platforms.*'))
                        <p class="field__error" id="event-platforms-error">{{ $errors->first('platforms') ?: $errors->first('platforms.*') }}</p>
                    @endif
                    <div class="event-create__chips" role="group" aria-label="{{ __('Selected platforms') }}" data-event-platform-chips hidden></div>
                </div>
            </div>
                <div class="event-create__step-actions" data-step-actions hidden>
                    <button class="button button--primary" type="button" data-step-next>{{ __('Next') }} <x-icon name="arrow-right" /></button>
                </div>
            </section>

            <section class="event-create__step" data-event-step="1" aria-labelledby="event-step-details">
                <h2 id="event-step-details">{{ __('Event details') }}</h2>
                <p class="field__hint">{{ __('Give your event a clear title and description.') }}</p>
                <div class="event-create__grid">

                <div class="event-create__title-field">
                    <x-field name="title" :label="__('Title')" required maxlength="255" />
                    <p class="field__hint event-create__title-count" data-title-count data-count-label="{{ __(':count of :max characters') }}"></p>
                </div>

                <div class="field event-create__description">
                    <label class="field__label" for="description">{{ __('Description') }}</label>
                    <textarea class="field__control" id="description" name="description" rows="6" required @if ($errors->has('description')) aria-invalid="true" aria-describedby="description-error" @endif>{{ old('description') }}</textarea>
                    @error('description')
                        <p class="field__error" id="description-error">{{ $message }}</p>
                    @enderror
                </div>
                </div>
                <div class="event-create__step-actions" data-step-actions hidden>
                    <button class="button" type="button" data-step-back>{{ __('Back') }}</button>
                    <button class="button button--primary" type="button" data-step-next>{{ __('Next') }} <x-icon name="arrow-right" /></button>
                </div>
            </section>

            <section class="event-create__step" data-event-step="2" aria-labelledby="event-step-schedule">
                <h2 id="event-step-schedule">{{ __('When and where') }}</h2>
                <p class="field__hint">{{ __('Set the place, time and number of players.') }}</p>
                <div class="event-create__grid">

                <fieldset class="event-create__location" data-event-location data-address-url="{{ route('events.addresses.search') }}" data-address-selected="{{ __('Address selected.') }}" data-address-empty="{{ __('No registered addresses found. Try another search.') }}" data-address-unavailable="{{ __('Address search is temporarily unavailable.') }}" data-address-prompt="{{ __('Choose an address from the suggestions.') }}">
                    <legend class="field__label">{{ __('Location') }}</legend>
                    <div class="event-create__location-modes">
                        <label class="event-create__location-mode">
                            <input type="radio" name="location_mode" value="search" @checked($locationMode === 'search')>
                            <span class="event-create__location-select" aria-hidden="true"></span>
                            <span class="event-create__location-mode-copy">
                                <span>{{ __('Find an address') }}</span>
                                <span class="event-create__location-mode-hint">{{ __('Select an existing address from the suggestions.') }}</span>
                            </span>
                        </label>
                        <label class="event-create__location-mode">
                            <input type="radio" name="location_mode" value="online" @checked($locationMode === 'online')>
                            <span class="event-create__location-select" aria-hidden="true"></span>
                            <span class="event-create__location-mode-copy">
                                <span>{{ __('Online') }}</span>
                                <span class="event-create__location-mode-hint">{{ __('This event takes place online.') }}</span>
                            </span>
                        </label>
                    </div>
                    <div class="event-create__location-panel" data-location-panel="search">
                        <label class="field__label" for="location_query">{{ __('Address') }}</label>
                        <div class="event-create__address-search">
                            <input class="field__control" id="location_query" name="event_address_search" type="search" value="{{ old('event_address_search') }}" placeholder="{{ __('Street, house number, postcode or city') }}" autocomplete="new-password" data-location-query aria-controls="location-results" aria-autocomplete="list">
                            <div class="event-create__location-results" id="location-results" data-location-results role="listbox" aria-label="{{ __('Address suggestions') }}" hidden></div>
                        </div>
                        <p class="field__hint" data-location-status aria-live="polite">{{ __('Choose an address from the suggestions. Leave empty if the location is not known yet.') }}</p>
                        @error('location_reference') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                    <input name="location_reference" type="hidden" value="{{ old('location_reference') }}" data-location-reference>
                    <div class="event-create__address-parts" data-address-parts aria-label="{{ __('Selected address details') }}" hidden>
                        @foreach (['street' => __('Street'), 'number' => __('House number'), 'postcode' => __('Postcode'), 'city' => __('City')] as $part => $label)
                            <label class="field">
                                <span class="field__label">{{ $label }}</span>
                                <input class="field__control" type="text" readonly data-address-part="{{ $part }}">
                            </label>
                        @endforeach
                    </div>
                    <p class="field__hint event-create__location-panel" data-location-panel="online">{{ __('Participants will see that this event is online.') }}</p>
                    @error('location_mode') <p class="field__error">{{ $message }}</p> @enderror
                    <p class="event-create__attribution event-create__location-panel" data-location-panel="search">{{ __('Location suggestions and address data are provided by') }} <a href="https://www.pdok.nl/" rel="noopener noreferrer">PDOK</a>.</p>
                </fieldset>

                <div class="event-create__schedule">
                    <div class="event-create__schedule-fallback" data-schedule-fallback>
                        <x-field name="starts_at" :label="__('Starts')" type="datetime-local" :value="$startValue" :min="now()->addMinute()->format('Y-m-d\TH:i')" data-starts-at required />
                        <x-field name="ends_at" :label="__('Ends (optional)')" type="datetime-local" :min="$startValue" data-ends-at />
                    </div>
                    <div data-schedule-enhanced data-add-end-label="{{ __('Add an end time') }}" hidden>
                        <span class="field__label" id="schedule-label">{{ __('Date and time') }}</span>
                        <details class="event-create__schedule-details" data-schedule-details>
                            <summary class="field__control event-create__schedule-summary" aria-labelledby="schedule-label schedule-summary" @if ($errors->has('starts_at') || $errors->has('ends_at')) aria-invalid="true" @endif>
                                <x-icon name="calendar" />
                                <span id="schedule-summary" data-schedule-summary></span>
                                <x-icon name="chevron-down" />
                            </summary>
                            <div class="event-create__schedule-panel" data-schedule-panel>
                                <div class="event-create__schedule-presets" aria-label="{{ __('Quick dates') }}">
                                    <button type="button" data-schedule-preset="today">{{ __('Today') }}</button>
                                    <button type="button" data-schedule-preset="tomorrow">{{ __('Tomorrow') }}</button>
                                    <button type="button" data-schedule-preset="week">{{ __('This week') }}</button>
                                    <button type="button" data-schedule-preset="next-week">{{ __('Next week') }}</button>
                                    <button type="button" data-schedule-preset="month">{{ __('This month') }}</button>
                                    <button type="button" data-schedule-preset="next-month">{{ __('Next month') }}</button>
                                </div>
                                <div class="event-create__schedule-main">
                                    <div class="event-create__schedule-fields" role="group" aria-label="{{ __('Date and time') }}">
                                        <button type="button" data-schedule-tab="start" aria-pressed="true"><span>{{ __('Start') }}</span><strong data-schedule-start-label></strong></button>
                                        <span aria-hidden="true">→</span>
                                        <button type="button" data-schedule-tab="end" aria-pressed="false"><span>{{ __('End (optional)') }}</span><strong data-schedule-end-label></strong></button>
                                    </div>
                                    <div class="event-create__schedule-calendars">
                                        <div class="event-create__schedule-calendar">
                                            <div class="event-create__calendar-head">
                                                <button type="button" data-calendar-previous aria-label="{{ __('Previous month') }}"><x-icon name="chevron-left" /></button>
                                                <strong data-calendar-month="0"></strong>
                                                <button type="button" data-calendar-next aria-label="{{ __('Next month') }}"><x-icon name="chevron-right" /></button>
                                            </div>
                                            <div class="event-create__calendar-weekdays" data-calendar-weekdays="0" aria-hidden="true"></div>
                                            <div class="event-create__calendar-days" data-calendar-days="0"></div>
                                        </div>
                                        <div class="event-create__schedule-calendar event-create__schedule-calendar--second">
                                            <div class="event-create__calendar-head">
                                                <span aria-hidden="true"></span>
                                                <strong data-calendar-month="1"></strong>
                                                <span aria-hidden="true"></span>
                                            </div>
                                            <div class="event-create__calendar-weekdays" data-calendar-weekdays="1" aria-hidden="true"></div>
                                            <div class="event-create__calendar-days" data-calendar-days="1"></div>
                                        </div>
                                    </div>
                                    <div class="event-create__schedule-time">
                                        <span class="field__label">{{ __('Time') }}</span>
                                        <label class="sr-only" for="schedule-hour">{{ __('Hour') }}</label>
                                        <span class="event-create__schedule-time-select"><select id="schedule-hour" class="field__control" data-schedule-hour></select><x-icon name="chevron-down" /></span>
                                        <span aria-hidden="true">:</span>
                                        <label class="sr-only" for="schedule-minute">{{ __('Minute') }}</label>
                                        <span class="event-create__schedule-time-select"><select id="schedule-minute" class="field__control" data-schedule-minute></select><x-icon name="chevron-down" /></span>
                                        <button type="button" class="event-create__schedule-remove" data-schedule-remove>{{ __('Remove end time') }}</button>
                                    </div>
                                </div>
                                <div class="event-create__schedule-actions">
                                    <x-button data-schedule-cancel>{{ __('Cancel') }}</x-button>
                                    <x-button variant="primary" data-schedule-apply>{{ __('Apply') }}</x-button>
                                </div>
                            </div>
                        </details>
                        @error('starts_at') <p class="field__error">{{ $message }}</p> @enderror
                        @error('ends_at') <p class="field__error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <fieldset class="event-create__capacity">
                    <legend class="field__label">{{ __('Participant limits') }}</legend>
                    <div class="event-create__capacity-options">
                        <label class="event-create__capacity-option">
                            <input class="checkbox__control" type="radio" name="capacity_mode" value="limited" @checked($capacityMode === 'limited') data-capacity-mode required>
                            <span>{{ __('Set a minimum and maximum') }}</span>
                        </label>
                        <label class="event-create__capacity-option">
                            <input class="checkbox__control" type="radio" name="capacity_mode" value="unlimited" @checked($capacityMode === 'unlimited') data-capacity-mode required>
                            <span>{{ __('No minimum or maximum') }}</span>
                        </label>
                    </div>
                    @error('capacity_mode')
                        <p class="field__error">{{ $message }}</p>
                    @enderror
                    <div class="event-create__capacity-fields" data-capacity-fields>
                        <x-field name="min_participants" :label="__('Minimum participants')" type="number" :value="2" min="1" max="65535" />
                        <x-field name="max_participants" :label="__('Maximum participants')" type="number" :value="12" min="1" max="65535" />
                    </div>
                </fieldset>

            </div>

            <div class="form__actions form__actions--start">
                <button class="button" type="button" data-step-back hidden>{{ __('Back') }}</button>
                <x-button variant="primary" type="submit">{{ __('Create event') }}</x-button>
                <x-button :href="route('dashboard')">{{ __('Cancel') }}</x-button>
            </div>
            </section>
        </form>
    @endif
</x-layouts.app>
