<x-layouts.app :title="__('Create an event')" robots="noindex, follow">
    <x-slot:header>
        <h1>{{ __('Create an event') }}</h1>
        <p class="page-header__intro">{{ __('Bring players together for a game you enjoy.') }}</p>
    </x-slot:header>

    @if (! $canOrganize)
        <section class="card event-create__eligibility" aria-labelledby="event-create-eligibility">
            <h2 class="card__title" id="event-create-eligibility">{{ __('Join :count events before organising', ['count' => $signUpsNeeded]) }}</h2>
            <p>{{ __('You have signed up for :count of the :needed events organised by other people that you need.', ['count' => $signUpsCount, 'needed' => $signUpsNeeded]) }}</p>
            <p>{{ trans_choice('Sign up for :count more event.|Sign up for :count more events.', $signUpsRemaining) }}</p>
            <div class="event-create__eligibility-action">
                <x-button variant="primary" :href="route('events.index')">{{ __('Find an event') }}</x-button>
            </div>
        </section>
    @else
        @include('partials.event-form', [
            'action' => route('events.store'),
            'submitLabel' => __('Create event'),
            'cancelUrl' => route('dashboard'),
        ])
    @endif
</x-layouts.app>
