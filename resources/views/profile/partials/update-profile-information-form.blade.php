<header class="card__header">
    <h2 class="card__title" id="profile-information-title">{{ __('Profile Information') }}</h2>

    <p class="card__intro">
        {{ __("Update your account's profile information and email address.") }}
    </p>
</header>

{{-- Its own form, submitted by a button inside the profile form through the
     `form` attribute: forms cannot nest, and this one posts elsewhere. --}}
<form id="send-verification" method="POST" action="{{ route('verification.send') }}">
    @csrf
</form>

<form class="form" method="POST" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <x-field
        name="name"
        :label="__('Name')"
        :value="$user->name"
        required
        autofocus
        autocomplete="name"
    />

    <x-field
        name="email"
        type="email"
        :label="__('Email')"
        :value="$user->email"
        required
        autocomplete="username"
    />

    @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
        <x-alert role="status" :dismissible="false">
            {{ __('Your email address is unverified.') }}

            <x-button variant="link" type="submit" form="send-verification">
                {{ __('Click here to re-send the verification email.') }}
            </x-button>

            @if (session('status') === 'verification-link-sent')
                <p class="form__notice">
                    {{ __('A new verification link has been sent to your email address.') }}
                </p>
            @endif
        </x-alert>
    @endif

    <div class="form__actions form__actions--start">
        <x-button variant="primary" type="submit">{{ __('Save') }}</x-button>

        @if (session('status') === 'profile-updated')
            <p class="form__saved" role="status" data-transient>{{ __('Saved.') }}</p>
        @endif
    </div>
</form>
