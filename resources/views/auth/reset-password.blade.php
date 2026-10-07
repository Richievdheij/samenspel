<x-layouts.guest :title="__('Reset Password')">
    <form class="form" method="POST" action="{{ route('password.store') }}">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <x-field
            name="email"
            type="email"
            :label="__('Email')"
            :value="$request->string('email')->toString()"
            required
            autofocus
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
            <x-button variant="primary" type="submit">
                {{ __('Reset Password') }}
            </x-button>
        </div>
    </form>
</x-layouts.guest>
