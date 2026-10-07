<x-layouts.app :title="__('Dashboard')">
    <x-slot:header>
        <h1>{{ __('Dashboard') }}</h1>
    </x-slot:header>

    <section class="card">
        <p>{{ __("You're logged in!") }}</p>
    </section>
</x-layouts.app>
