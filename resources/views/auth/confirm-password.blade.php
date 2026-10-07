<x-layouts.guest :title="__('Confirm Password')">
    <p class="card__intro">
        {{ __('This is a secure area of the application. Please confirm your password before continuing.') }}
    </p>

    <form class="form" method="POST" action="{{ route('password.confirm') }}">
        @csrf

        <x-field
            name="password"
            type="password"
            :label="__('Password')"
            required
            autofocus
            autocomplete="current-password"
        />

        <div class="form__actions">
            <x-button variant="primary" type="submit">
                {{ __('Confirm') }}
            </x-button>
        </div>
    </form>
</x-layouts.guest>
