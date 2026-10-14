{{--
    The <head> both layouts share, so the guest pages and the app pages cannot
    drift apart on the viewport, the CSRF meta tag or the asset entry points.

    Expects $title, $description, $canonical and $robots from the layout.
--}}
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>document.documentElement.classList.add('js')</script>

@php
    $pageTitle = $title ? $title.' — '.config('app.name') : config('app.name');
    $pageDescription = $description ?? __('Bring players together for gaming events and LAN parties on Samenspel.');
    $pageUrl = $canonical ?? request()->url();
@endphp

<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
@if ($canonical ?? null)
    <link rel="canonical" href="{{ $canonical }}">
@endif
<meta name="robots" content="{{ $robots ?? 'index, follow' }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:url" content="{{ $pageUrl }}">
<meta property="og:locale" content="{{ app()->getLocale() === 'nl' ? 'nl_NL' : 'en_US' }}">
<meta name="twitter:card" content="summary">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">

{{-- The brand icons from public/. The SVG favicon is preferred; the ICO
     carries 16, 32 and 48 pixels for browsers without SVG favicons. The
     theme colour is --color-felt, written out because a meta tag cannot read
     a custom property. --}}
<link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<link rel="manifest" href="{{ asset('site.webmanifest') }}">
<meta name="theme-color" content="#1F4D3A">
<link rel="preload" href="{{ Vite::asset('resources/fonts/bricolage-grotesque/BricolageGrotesque-variable-latin.woff2') }}" as="font" type="font/woff2" crossorigin>

{{-- Preload the stylesheet and apply it when ready, keeping CSS off the critical
     render path. The no-script link preserves styling without JavaScript. --}}
@php
    Vite::useStyleTagAttributes(['media' => 'print', 'onload' => "this.media='all'"]);
@endphp
@vite(['resources/scss/main.scss', 'resources/js/app.js'])
<noscript>
    <link rel="stylesheet" href="{{ Vite::asset('resources/scss/main.scss') }}">
</noscript>
