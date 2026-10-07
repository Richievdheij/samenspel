<header class="card__header">
    <h2 class="card__title" id="delete-account-title">{{ __('Delete Account') }}</h2>

    <p class="card__intro">
        {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
    </p>
</header>

<x-button variant="danger" command="show-modal" commandfor="confirm-user-deletion">
    {{ __('Delete Account') }}
</x-button>

<x-modal
    name="confirm-user-deletion"
    :show="$errors->userDeletion->isNotEmpty()"
    aria-labelledby="confirm-user-deletion-title"
>
    <form class="form" method="POST" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <h2 class="modal__title" id="confirm-user-deletion-title">
            {{ __('Are you sure you want to delete your account?') }}
        </h2>

        <p class="modal__text">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
        </p>

        <x-field
            name="password"
            id="delete_user_password"
            type="password"
            bag="userDeletion"
            :label="__('Password')"
            :placeholder="__('Password')"
            required
            autofocus
            autocomplete="current-password"
        />

        <div class="form__actions form__actions--end">
            <x-button command="close" commandfor="confirm-user-deletion">
                {{ __('Cancel') }}
            </x-button>

            <x-button variant="danger" type="submit">
                {{ __('Delete Account') }}
            </x-button>
        </div>
    </form>
</x-modal>
