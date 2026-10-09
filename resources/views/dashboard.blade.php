{{--
    The signed-in start page. For now it points at the one thing to do next:
    find an event to join.
--}}
<x-layouts.app :title="__('Dashboard')">
    <x-slot:header>
        <h1>{{ __('Dashboard') }}</h1>
        <p class="page-header__intro">{{ __('Welcome back, :name.', ['name' => auth()->user()->name]) }}</p>
    </x-slot:header>

    <section class="card">
        <h2 class="card__title">{{ __('Find an event') }}</h2>
        <p class="card__intro">
            {{ __('Sign up for an event someone else organises. After three sign-ups you can organise one yourself.') }}
        </p>

        <x-button variant="primary" :href="route('events.index')">
            {{ __('View the events') }}
        </x-button>
    </section>
</x-layouts.app>
