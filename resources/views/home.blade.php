{{--
    The landing page a fresh project starts from.

    Deliberately empty: one button, centred in the content area, and nothing
    else. It exists so `/` serves something, so the layout, the SCSS layers and
    the Vite manifest are exercised by a real request, and so the browser smoke
    test has a page to assert against. Replace the contents; keep the shape.

    The <h1> is for screen readers only. A page without a heading is one a
    screen reader user cannot jump into, even when sighted users need none.
--}}
<x-layouts.app>
    <div class="home">
        <h1 class="sr-only">{{ config('app.name') }}</h1>

        <x-button variant="primary" href="https://laravel.com/docs">
            {{ __('Laravel documentation') }}
        </x-button>
    </div>
</x-layouts.app>
