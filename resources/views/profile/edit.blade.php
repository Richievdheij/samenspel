<x-layouts.app :title="__('Profile')" :back="route('dashboard')" :back-label="__('Back to dashboard')">
    <x-slot:header>
        <h1>{{ __('Profile') }}</h1>
    </x-slot:header>

    <div class="stack">
        <section class="card" aria-labelledby="profile-information-title">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="card" aria-labelledby="update-password-title">
            @include('profile.partials.update-password-form')
        </section>

        <section class="card" aria-labelledby="delete-account-title">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-layouts.app>
