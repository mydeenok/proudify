<section>
    <header>
        <h2 class="font-headline-md text-headline-md text-on-surface">Update Password</h2>
        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Ensure your account is using a long, random password to stay secure.</p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-lg space-y-md">
        @csrf
        @method('put')

        <div>
            <x-input-label for="update_password_current_password" value="Current password" />
            <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" />
        </div>

        <div>
            <x-input-label for="update_password_password" value="New password" />
            <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" value="Confirm password" />
            <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" />
        </div>

        <div class="flex items-center gap-md">
            <x-primary-button>Save</x-primary-button>

            @if (session('status') === 'password-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="font-body-sm text-body-sm text-on-surface-variant">
                    Saved.
                </p>
            @endif
        </div>
    </form>
</section>
