{{--
    The shell for the pages a signed-out visitor sees: login, registration, the
    password reset pair, and the two interstitials (verify email, confirm
    password). One centred card under the logo, no navigation — the same
    shape as Breeze's guest layout.

        <x-layouts.guest :title="__('Log in')">…form…</x-layouts.guest>

    Props — Blade has no typed props, so this block IS the contract.

      string|null $title  prepended to the app name in <title>, and the card's
                          visible heading; omit for neither

    Styles: resources/scss/layout/_guest.scss and components/_card.scss
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>

    <body>
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

        <div class="guest">
            <a class="guest__brand" href="{{ route('home') }}">
                <x-logo layout="stacked" />
            </a>

            <main class="guest__main card" id="main" tabindex="-1">
                @if ($title)
                    <h1 class="card__title">{{ $title }}</h1>
                @endif

                @include('partials.flash')

                {{ $slot }}
            </main>
        </div>
    </body>
</html>
