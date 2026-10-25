<x-layouts.app :title="__('Manage categories')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Manage categories') }}</h1>
        <p class="page-header__intro">{{ __('Add, rename and remove the categories organisers choose from.') }}</p>
    </x-slot:header>

    <section class="card" aria-labelledby="add-category">
        <h2 class="card__title" id="add-category">{{ __('Add a category') }}</h2>

        <form class="admin-search" method="POST" action="{{ route('admin.categories.store') }}">
            @csrf
            <x-field name="name" :label="__('Name')" required maxlength="50" />
            <x-button variant="primary" type="submit">{{ __('Add') }}</x-button>
        </form>
    </section>

    <section class="card admin-section" aria-labelledby="all-categories">
        <h2 class="card__title" id="all-categories">{{ __('All categories (:count)', ['count' => $categories->total()]) }}</h2>
        @error('category')
            <p class="field__error" role="alert">{{ $message }}</p>
        @enderror

        @if ($categories->isEmpty())
            <p>{{ __('There are no categories yet.') }}</p>
        @else
            <ul class="admin-list">
                @foreach ($categories as $category)
                    <li class="admin-list__item">
                        <span class="admin-list__name">{{ $category->name }}</span>
                        <span class="admin-list__meta">{{ trans_choice(':count event|:count events', $category->tagged_events_count) }}</span>
                        <div class="admin-list__actions">
                            <x-button :href="route('admin.categories.edit', $category)" :aria-label="__('Edit :name', ['name' => $category->name])">{{ __('Edit') }}</x-button>
                            @if ($category->tagged_events_count === 0)
                                <form method="POST" action="{{ route('admin.categories.destroy', $category) }}">
                                    @csrf
                                    @method('DELETE')
                                    <x-button variant="danger" type="submit" :aria-label="__('Delete :name', ['name' => $category->name])">{{ __('Delete') }}</x-button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
            {{ $categories->links() }}
        @endif
    </section>
</x-layouts.app>
