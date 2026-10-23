<x-layouts.app :title="__('Edit :name', ['name' => $game->name])" :back="route('admin.games.index')" :back-label="__('Back to games')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Edit :name', ['name' => $game->name]) }}</h1>
    </x-slot:header>

    <form class="card form" method="POST" action="{{ route('admin.games.update', $game) }}" enctype="multipart/form-data">
        @csrf
        @method('PUT')

        <x-field name="name" :label="__('Title')" :value="$game->name" required maxlength="255" :hint="__('The title Samenspel shows on event cards and pages.')" />

        @if ($game->cover_image_path || $game->cover_image_url)
            <img class="admin-cover" src="{{ $game->cover_image_path ? Storage::disk('public')->url($game->cover_image_path) : $game->cover_image_url }}" alt="{{ __('Current cover of :name', ['name' => $game->name]) }}" width="120">
        @endif

        <x-field name="cover" :label="__('Own cover (optional)')" type="file" accept="image/jpeg,image/png,image/webp" :hint="__('JPG, PNG or WebP, at most 2 MB. Replaces the catalog cover.')" />

        @if ($game->cover_image_path)
            <div class="checkbox">
                <input class="checkbox__control" id="remove_cover" type="checkbox" name="remove_cover" value="1">
                <label class="checkbox__label" for="remove_cover">{{ __('Remove the own cover and use the catalog cover again') }}</label>
            </div>
        @endif

        <div class="form__actions form__actions--start">
            <x-button variant="primary" type="submit">{{ __('Save changes') }}</x-button>
            <x-button :href="route('admin.games.index')">{{ __('Cancel') }}</x-button>
        </div>
    </form>
</x-layouts.app>
