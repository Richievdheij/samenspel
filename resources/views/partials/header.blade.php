{{--
    The site header.

    From the `m` breakpoint up: the logo, the main links in a line-art pill in
    the centre, and the account area on the right — a log-in link and a register
    button for a guest, an avatar chip that opens the user menu for someone
    signed in. Below `m`: the logo and a menu button that slides a full-width
    menu in from the left, with the logo and the close button where they were.

    Both menus are native <details>, so they open and close with no JavaScript at
    all. resources/js/app.js adds what <details> lacks: a closing animation,
    Escape and a click outside to close, and the close button inside the drawer,
    which stays hidden without it.

    aria-current marks the current page for a screen reader; sighted readers see
    it as a filled segment, never as colour alone. Logging out is a form with a
    real button, because it changes state: a link would be followed by any
    prefetcher that sees it.
--}}
@php
    $links = [
        ['route' => 'home', 'label' => __('Home'), 'icon' => 'home'],
        ['route' => 'events.index', 'label' => __('Events'), 'icon' => 'calendar'],
    ];

    if (auth()->check()) {
        $links[] = ['route' => 'dashboard', 'label' => __('Dashboard'), 'icon' => 'dashboard'];
    }
@endphp

<header class="site-header">
    <div class="container site-header__inner">
        <a class="site-header__brand" href="{{ route('home') }}">
            <x-logo />
        </a>

        <nav class="site-header__nav" aria-label="{{ __('Main navigation') }}">
            @foreach ($links as $link)
                <a
                    class="site-header__link"
                    href="{{ route($link['route']) }}"
                    @if (request()->routeIs($link['route'])) aria-current="page" @endif
                >
                    {{ $link['label'] }}
                </a>
            @endforeach
        </nav>

        <div class="site-header__account">
            @auth
                <details class="dropdown" data-disclosure>
                    <summary class="dropdown__toggle">
                        <span class="avatar" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                        <span class="dropdown__label">{{ auth()->user()->name }}</span>
                        <x-icon name="chevron-down" class="dropdown__chevron" />
                    </summary>

                    <div class="dropdown__menu" data-disclosure-panel>
                        <div class="dropdown__meta">
                            <span class="dropdown__name">{{ auth()->user()->name }}</span>
                            <span class="dropdown__email">{{ auth()->user()->email }}</span>
                        </div>

                        <a
                            class="dropdown__item"
                            href="{{ route('profile.edit') }}"
                            @if (request()->routeIs('profile.edit')) aria-current="page" @endif
                        >
                            <x-icon name="user" />
                            {{ __('Profile') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown__item" type="submit">
                                <x-icon name="log-out" />
                                {{ __('Log Out') }}
                            </button>
                        </form>
                    </div>
                </details>
            @else
                <a class="site-header__login" href="{{ route('login') }}">{{ __('Log in') }}</a>

                @if (Route::has('register'))
                    <x-button variant="primary" :href="route('register')">{{ __('Register') }}</x-button>
                @endif
            @endauth
        </div>

        <details class="site-menu" data-disclosure>
            <summary class="site-menu__toggle">
                <span class="sr-only">{{ __('Menu') }}</span>
                {{-- Three bars that fold into a cross when the menu opens. --}}
                <svg class="site-menu__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <path class="site-menu__bar site-menu__bar--top" d="M4 6h16" />
                    <path class="site-menu__bar site-menu__bar--middle" d="M4 12h16" />
                    <path class="site-menu__bar site-menu__bar--bottom" d="M4 18h16" />
                </svg>
            </summary>

            {{-- Full width. Its top row is built like the header row above —
                 same height, same container, same logo — and the close button
                 sits exactly where the menu button was, in its pressed state, so
                 opening the menu changes what is below the bar, not the bar. --}}
            <div class="site-menu__panel" data-disclosure-panel>
                <div class="site-menu__top">
                    <div class="container site-menu__top-inner">
                        <a class="site-menu__brand" href="{{ route('home') }}">
                            <x-logo />
                        </a>

                        <button class="site-menu__close" type="button" data-disclosure-close hidden>
                            <span class="sr-only">{{ __('Close menu') }}</span>
                            <x-icon name="x" class="site-menu__close-icon" />
                        </button>
                    </div>
                </div>

                <div class="container site-menu__body">
                    <nav class="site-menu__nav" aria-label="{{ __('Main navigation') }}">
                        <p class="site-menu__heading">{{ __('Menu') }}</p>

                        @foreach ($links as $link)
                            <a
                                class="site-menu__link"
                                href="{{ route($link['route']) }}"
                                style="--item: {{ $loop->iteration }}"
                                @if (request()->routeIs($link['route'])) aria-current="page" @endif
                            >
                                <x-icon :name="$link['icon']" class="site-menu__link-icon" />
                                <span class="site-menu__link-label">{{ $link['label'] }}</span>
                                <x-icon name="arrow-right" class="site-menu__link-arrow" />
                            </a>
                        @endforeach
                    </nav>

                    <div class="site-menu__account" style="--item: {{ count($links) + 1 }}">
                        @auth
                            <div class="site-menu__user">
                                <span class="avatar avatar--large" aria-hidden="true">{{ auth()->user()->initials() }}</span>
                                <div class="site-menu__who">
                                    <span class="site-menu__name">{{ auth()->user()->name }}</span>
                                    <span class="site-menu__email">{{ auth()->user()->email }}</span>
                                </div>
                            </div>

                            <div class="site-menu__actions">
                                <x-button
                                    variant="block"
                                    :href="route('profile.edit')"
                                    :aria-current="request()->routeIs('profile.edit') ? 'page' : null"
                                >
                                    <x-icon name="user" />
                                    {{ __('Profile') }}
                                </x-button>

                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <x-button variant="block" type="submit">
                                        <x-icon name="log-out" />
                                        {{ __('Log Out') }}
                                    </x-button>
                                </form>
                            </div>
                        @else
                            <div class="site-menu__actions">
                                <x-button variant="block" :href="route('login')">{{ __('Log in') }}</x-button>

                                @if (Route::has('register'))
                                    <x-button class="button--primary" variant="block" :href="route('register')">{{ __('Register') }}</x-button>
                                @endif
                            </div>
                        @endauth
                    </div>
                </div>

                <x-memphis variant="drawer" />
            </div>
        </details>
    </div>
</header>
