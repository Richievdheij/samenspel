{{--
    The page shell, as a component layout: a page writes <x-layouts.app> and its
    body arrives in {{ $slot }}. This is the current Laravel convention and it
    beats @extends/@section here because attributes like :title are ordinary
    component props with real defaults.

    Everything the browser needs is declared once, in partials/head. A page that
    needs another stylesheet has outgrown the template, not this layout.

    Props — Blade has no typed props, so this block IS the contract. `@props`
    carries names and defaults only; the compiler never checks a type.

      string|null $title   prepended to the app name in <title>; omit for the
                           app name alone

    Slots

      $header  optional page heading, rendered in a band above the content:
               <x-slot:header><h1>{{ __('Profile') }}</h1></x-slot:header>

    Styles: resources/scss/layout/_site.scss (frame) and _container.scss (width)
--}}
@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>

    <body>
        {{-- First focusable element on the page. Without it a keyboard user tabs
             through the whole navigation before reaching the content, on every
             page. --}}
        <a class="skip-link" href="#main">{{ __('Skip to content') }}</a>

        <div class="site">
            {{-- The header and the content together fill exactly one screen,
                 however little the page holds; the footer starts below it. --}}
            <div class="site__screen">
                @include('partials.header')

                <main class="site__main" id="main" tabindex="-1">
                    @isset($header)
                        <div class="page-header">
                            <x-memphis variant="header" />

                            <div class="container page-header__inner">
                                {{ $header }}
                            </div>
                        </div>
                    @endisset

                    <div class="container site__content">
                        @include('partials.flash')

                        {{ $slot }}
                    </div>
                </main>
            </div>

            @include('partials.footer')
        </div>
    </body>
</html>
