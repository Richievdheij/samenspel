{{--
    The site header: the same navigation Breeze ships, without Alpine.

    Two disclosures, both native <details>, so both open and close with no
    JavaScript at all: the user menu from the `m` breakpoint up, and the
    hamburger menu below it. resources/js/app.js adds what <details> lacks —
    Escape closes and returns focus to the toggle, and a click outside closes.

    Every link is plain text. aria-current tells a screen reader which one is the
    current page; sighted readers get the same signal as weight, never as colour
    alone. Logging out is a form with a real button, because it
    changes state: a link would be followed by any prefetcher that sees it.
--}}
@php
    $links = [
        ['route' => 'home', 'label' => __('Home')],
    ];

    if (auth()->check()) {
        $links[] = ['route' => 'dashboard', 'label' => __('Dashboard')];
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
                    <summary class="site-header__link dropdown__toggle">
                        {{ auth()->user()->name }}
                        <svg class="dropdown__chevron" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </summary>

                    <div class="dropdown__menu">
                        <div class="dropdown__meta">
                            <span class="dropdown__name">{{ auth()->user()->name }}</span>
                            <span class="dropdown__email">{{ auth()->user()->email }}</span>
                        </div>

                        <a
                            class="dropdown__item"
                            href="{{ route('profile.edit') }}"
                            @if (request()->routeIs('profile.edit')) aria-current="page" @endif
                        >
                            {{ __('Profile') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="dropdown__item" type="submit">{{ __('Log Out') }}</button>
                        </form>
                    </div>
                </details>
            @else
                <a class="site-header__link" href="{{ route('login') }}">{{ __('Log in') }}</a>

                @if (Route::has('register'))
                    <a class="site-header__link" href="{{ route('register') }}">{{ __('Register') }}</a>
                @endif
            @endauth
        </div>

        <details class="site-menu" data-disclosure>
            <summary class="site-header__link site-menu__toggle">
                <span class="sr-only">{{ __('Menu') }}</span>
                <svg class="site-menu__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">
                    <path class="site-menu__open" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    <path class="site-menu__close" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </summary>

            <div class="site-menu__panel">
                <nav class="site-menu__section" aria-label="{{ __('Main navigation') }}">
                    @foreach ($links as $link)
                        <a
                            class="site-menu__link"
                            href="{{ route($link['route']) }}"
                            @if (request()->routeIs($link['route'])) aria-current="page" @endif
                        >
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </nav>

                <div class="site-menu__section">
                    @auth
                        <div class="site-menu__meta">
                            <span class="site-menu__name">{{ auth()->user()->name }}</span>
                            <span class="site-menu__email">{{ auth()->user()->email }}</span>
                        </div>

                        <a
                            class="site-menu__link"
                            href="{{ route('profile.edit') }}"
                            @if (request()->routeIs('profile.edit')) aria-current="page" @endif
                        >
                            {{ __('Profile') }}
                        </a>

                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="site-menu__link" type="submit">{{ __('Log Out') }}</button>
                        </form>
                    @else
                        <a class="site-menu__link" href="{{ route('login') }}">{{ __('Log in') }}</a>

                        @if (Route::has('register'))
                            <a class="site-menu__link" href="{{ route('register') }}">{{ __('Register') }}</a>
                        @endif
                    @endauth
                </div>
            </div>
        </details>
    </div>
</header>
