<x-layouts.app :title="__('Edit event')" :back="route('events.show', $event)" :back-label="__('Back to event')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Edit event') }}</h1>
        <p class="page-header__intro">{{ $event->title }}</p>
    </x-slot:header>

    @include('partials.event-form', [
        'action' => route('events.update', $event),
        'submitLabel' => __('Save changes'),
        'cancelUrl' => route('events.show', $event),
    ])
</x-layouts.app>
