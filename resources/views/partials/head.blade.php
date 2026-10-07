{{--
    The <head> both layouts share, so the guest pages and the app pages cannot
    drift apart on the viewport, the CSRF meta tag or the asset entry points.

    Expects $title (string|null) from the including layout.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

{{-- One entry point each. @vite injects the dev-server tags in local and
     the hashed manifest paths in production, so nothing here changes
     between environments. --}}
@vite(['resources/scss/main.scss', 'resources/js/app.js'])
