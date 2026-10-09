{{--
    The landing page: a heading that says what to do here, one sentence on what
    Samenspel is, and two ways in. The events come first, because a guest can
    browse them without an account; registering is the second step. Beside it,
    the isometric game table and its Memphis shapes.

    The header already carries the logo, so the page does not repeat it. It also
    keeps the layout, the SCSS layers and the Vite manifest exercised by a real
    request, which the browser smoke test relies on.
--}}
<x-layouts.app>
    <div class="home">
        <div class="home__text">
            <p class="home__eyebrow">{{ __('Gaming events and LAN parties') }}</p>

            <h1 class="home__title">{{ __('Find your next LAN party.') }}</h1>

            <p class="home__lead">{{ __('Organise gaming events and LAN parties, and join in.') }}</p>

            <div class="home__actions">
                <x-button variant="primary" :href="route('events.index')">
                    {{ __('View the events') }}
                </x-button>

                @auth
                    <x-button :href="route('dashboard')">
                        {{ __('Go to your dashboard') }}
                    </x-button>
                @else
                    <x-button :href="route('register')">
                        {{ __('Create an account') }}
                    </x-button>
                @endauth
            </div>
        </div>

        <div class="home__art">
            <x-memphis variant="hero" />
            <x-iso-scene class="home__scene" />
        </div>
    </div>
</x-layouts.app>
