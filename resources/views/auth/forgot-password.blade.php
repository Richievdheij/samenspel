<x-layouts.guest :title="__('Forgot your password?')">
    <p class="card__intro">
        {{ __('Forgot your password? No problem. Just let us know your email address and we will email you a password reset link that will allow you to choose a new one.') }}
    </p>

    <form class="form" method="POST" action="{{ route('password.email') }}">
        @csrf

        <x-field
            name="email"
            type="email"
            :label="__('Email')"
            required
            autofocus
            autocomplete="username"
        />

        <div class="form__actions">
            <a href="{{ route('login') }}">{{ __('Back to log in') }}</a>

            <x-button variant="primary" type="submit">
                {{ __('Email Password Reset Link') }}
            </x-button>
        </div>
    </form>
</x-layouts.guest>
