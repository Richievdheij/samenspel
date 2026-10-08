{{--
    The <head> both layouts share, so the guest pages and the app pages cannot
    drift apart on the viewport, the CSRF meta tag or the asset entry points.

    Expects $title (string|null) from the including layout.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">

<title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

{{-- The brand icons from public/. The SVG favicon is preferred; the ICO
     carries 16, 32 and 48 pixels for browsers without SVG favicons. The
     theme colour is --color-felt, written out because a meta tag cannot read
     a custom property. --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#1F4D3A">

{{-- One entry point each. @vite injects the dev-server tags in local and
     the hashed manifest paths in production, so nothing here changes
     between environments. --}}
@vite(['resources/scss/main.scss', 'resources/js/app.js'])
