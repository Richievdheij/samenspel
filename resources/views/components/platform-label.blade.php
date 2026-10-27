@props(['platform'])

<span {{ $attributes->class(['platform-label', 'platform-label--icon', 'platform-label--'.$platform->slug]) }} role="img" aria-label="{{ __($platform->name) }}" title="{{ __($platform->name) }}"><x-platform-icon :slug="$platform->slug" /></span>
