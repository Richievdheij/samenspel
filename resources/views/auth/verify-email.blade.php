<x-layouts.guest :title="__('Verify Email Address')">
    <p class="card__intro">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <x-alert variant="success" role="status" :dismissible="false">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </x-alert>
    @endif

    <div class="form__actions">
        <form method="POST" action="{{ route('verification.send') }}">
            @csrf

            <x-button variant="primary" type="submit">
                {{ __('Resend Verification Email') }}
            </x-button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf

            <x-button variant="link" type="submit">
                {{ __('Log Out') }}
            </x-button>
        </form>
    </div>
</x-layouts.guest>
