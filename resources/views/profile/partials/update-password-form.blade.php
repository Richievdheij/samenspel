{{-- Validated into the `updatePassword` error bag, and every id is prefixed:
     the delete form further down the page has a `password` field of its own. --}}
<header class="card__header">
    <h2 class="card__title" id="update-password-title">{{ __('Update Password') }}</h2>

    <p class="card__intro">
        {{ __('Ensure your account is using a long, random password to stay secure.') }}
    </p>
</header>

<form class="form" method="POST" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <x-field
        name="current_password"
        id="update_password_current_password"
        type="password"
        bag="updatePassword"
        :label="__('Current Password')"
        required
        autocomplete="current-password"
    />

    <x-field
        name="password"
        id="update_password_password"
        type="password"
        bag="updatePassword"
        :label="__('New Password')"
        required
        autocomplete="new-password"
    />

    <x-field
        name="password_confirmation"
        id="update_password_password_confirmation"
        type="password"
        bag="updatePassword"
        :label="__('Confirm Password')"
        required
        autocomplete="new-password"
    />

    <div class="form__actions form__actions--start">
        <x-button variant="primary" type="submit">{{ __('Save') }}</x-button>

        @if (session('status') === 'password-updated')
            <p class="form__saved" role="status" data-transient>{{ __('Saved.') }}</p>
        @endif
    </div>
</form>
