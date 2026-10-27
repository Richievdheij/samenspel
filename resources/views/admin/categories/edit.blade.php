<x-layouts.app :title="__('Edit :name', ['name' => $category->name])" :back="route('admin.categories.index')" :back-label="__('Back to categories')">
    <x-slot:header>
        <h1>{{ __('Edit :name', ['name' => $category->name]) }}</h1>
    </x-slot:header>

    <form class="card form" method="POST" action="{{ route('admin.categories.update', $category) }}">
        @csrf
        @method('PUT')

        <x-field name="name" :label="__('Name')" :value="$category->name" required maxlength="50" />

        <div class="form__actions form__actions--start">
            <x-button variant="primary" type="submit">{{ __('Save changes') }}</x-button>
            <x-button :href="route('admin.categories.index')">{{ __('Cancel') }}</x-button>
        </div>
    </form>
</x-layouts.app>
