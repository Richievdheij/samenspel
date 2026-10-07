<x-layouts.guest :title="__('Register')">
    <form class="form" method="POST" action="{{ route('register') }}">
        @csrf

        <x-field
            name="name"
            :label="__('Name')"
            required
            autofocus
            autocomplete="name"
        />

        <x-field
            name="email"
            type="email"
            :label="__('Email')"
            required
            autocomplete="username"
        />

        <x-field
            name="password"
            type="password"
            :label="__('Password')"
            required
            autocomplete="new-password"
        />

        <x-field
            name="password_confirmation"
            type="password"
            :label="__('Confirm Password')"
            required
            autocomplete="new-password"
        />

        <div class="form__actions">
            <a href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-button variant="primary" type="submit">
                {{ __('Register') }}
            </x-button>
        </div>
    </form>
</x-layouts.guest>
