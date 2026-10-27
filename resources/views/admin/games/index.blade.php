<x-layouts.app :title="__('Manage games')">
    <x-slot:header>
        <h1>{{ __('Manage games') }}</h1>
        <p class="page-header__intro">{{ __('Add games from the IGDB catalog and choose how Samenspel shows them.') }}</p>
    </x-slot:header>

    <section class="card" aria-labelledby="add-game">
        <h2 class="card__title" id="add-game">{{ __('Add a game from the catalog') }}</h2>

        <form class="admin-search" method="GET" action="{{ route('admin.games.index') }}" role="search">
            <x-field name="q" :label="__('Search the catalog')" type="search" :value="$search" minlength="3" maxlength="80" />
            <x-button type="submit">{{ __('Search') }}</x-button>
        </form>

        @if ($catalogError)
            <p class="field__error" role="alert">{{ $catalogError }}</p>
        @elseif ($results !== [])
            <ul class="admin-list">
                @foreach ($results as $result)
                    <li class="admin-list__item">
                        <span class="admin-list__name">
                            {{ $result['name'] }}
                            @if ($result['release_year'])
                                <span class="admin-list__meta">({{ $result['release_year'] }})</span>
                            @endif
                        </span>
                        @if (in_array($result['id'], $addedProviderIds, true))
                            <span class="admin-list__meta">{{ __('Already added') }}</span>
                        @else
                            <form method="POST" action="{{ route('admin.games.store') }}">
                                @csrf
                                <input type="hidden" name="provider_id" value="{{ $result['id'] }}">
                                <x-button variant="primary" type="submit" :aria-label="__('Add :name', ['name' => $result['name']])">{{ __('Add') }}</x-button>
                            </form>
                        @endif
                    </li>
                @endforeach
            </ul>
        @elseif ($search !== '')
            <p>{{ __('No games found in the catalog.') }}</p>
        @endif
    </section>

    <section class="card admin-section" aria-labelledby="all-games">
        <h2 class="card__title" id="all-games">{{ __('All games (:count)', ['count' => $games->total()]) }}</h2>
        @error('game')
            <p class="field__error" role="alert">{{ $message }}</p>
        @enderror

        @if ($games->isEmpty())
            <p>{{ __('There are no games yet. Add one from the catalog.') }}</p>
        @else
            <ul class="admin-list">
                @foreach ($games as $game)
                    <li class="admin-list__item">
                        <span class="admin-list__name">
                            {{ $game->name }}
                            @if ($game->source === \App\Models\Game::SOURCE_RETIRED_CATALOG)
                                <span class="admin-list__meta">{{ __('Retired') }}</span>
                            @elseif ($game->source !== \App\Models\Game::SOURCE_CATALOG)
                                <span class="admin-list__meta">{{ __('Typed in by hand') }}</span>
                            @endif
                        </span>
                        <span class="admin-list__meta">{{ trans_choice(':count event|:count events', $game->selected_events_count) }}</span>
                        <div class="admin-list__actions">
                            <x-button :href="route('admin.games.edit', $game)" :aria-label="__('Edit :name', ['name' => $game->name])">{{ __('Edit') }}</x-button>
                            @if ($game->selected_events_count === 0)
                                <form method="POST" action="{{ route('admin.games.destroy', $game) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="danger" type="submit" :aria-label="__('Delete :name', ['name' => $game->name])">{{ __('Delete') }}</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $games->links() }}
        @endif
    </section>
</x-layouts.app>
