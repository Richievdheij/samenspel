{{--
    The site footer, on every page, below the fold: the header and the content
    fill the first screen, and the footer only appears once you scroll.

    Every link is a named route that exists, and the account column follows the
    visitor: log in and register for a guest, the dashboard and profile for
    someone signed in. Logging out stays in the user menu, because it is a POST.
--}}
<footer class="site-footer">
    <div class="container site-footer__inner">
        <div class="site-footer__brand">
            <a class="site-footer__logo" href="{{ route('home') }}">
                <x-logo variant="dark" />
            </a>

            <p class="site-footer__tagline">{{ __('Organise gaming events and LAN parties, and join in.') }}</p>
        </div>

        <nav class="site-footer__nav" aria-label="{{ __('Footer') }}">
            <div class="site-footer__group">
                <h2 class="site-footer__heading">{{ __('Discover') }}</h2>

                <ul class="site-footer__links">
                    <li><a class="site-footer__link" href="{{ route('home') }}">{{ __('Home') }}</a></li>
                    <li><a class="site-footer__link" href="{{ route('events.index') }}">{{ __('Events') }}</a></li>
                </ul>
            </div>

            <div class="site-footer__group">
                <h2 class="site-footer__heading">{{ __('Account') }}</h2>

                <ul class="site-footer__links">
                    @auth
                        <li><a class="site-footer__link" href="{{ route('dashboard') }}">{{ __('Dashboard') }}</a></li>
                        <li><a class="site-footer__link" href="{{ route('profile.edit') }}">{{ __('Profile') }}</a></li>
                    @else
                        <li><a class="site-footer__link" href="{{ route('login') }}">{{ __('Log in') }}</a></li>

                        @if (Route::has('register'))
                            <li><a class="site-footer__link" href="{{ route('register') }}">{{ __('Register') }}</a></li>
                        @endif
                    @endauth
                </ul>
            </div>
        </nav>
    </div>

    <div class="site-footer__bottom">
        <div class="container">
            <p>© {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}</p>
        </div>
    </div>
</footer>
