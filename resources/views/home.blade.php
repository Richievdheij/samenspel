{{--
    The landing page, until the event overview takes its place.

    The stacked logo, one sentence saying what Samenspel is and one action:
    register for a guest, the dashboard for someone signed in. It also keeps the
    layout, the SCSS layers and the Vite manifest exercised by a real request,
    which the browser smoke test relies on.

    The <h1> is for screen readers only. The logo already shows the name, and a
    page without a heading is one a screen reader user cannot jump into.
--}}
<x-layouts.app>
    <div class="home">
        <h1 class="sr-only">{{ config('app.name') }}</h1>

        <x-logo layout="stacked" />

        <p class="home__lead">{{ __('Organise gaming events and LAN parties, and join in.') }}</p>

        @auth
            <x-button variant="primary" :href="route('dashboard')">
                {{ __('Go to your dashboard') }}
            </x-button>
        @else
            <x-button variant="primary" :href="route('register')">
                {{ __('Create an account') }}
            </x-button>
        @endauth
    </div>
</x-layouts.app>
