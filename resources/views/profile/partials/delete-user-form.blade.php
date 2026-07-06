<section class="space-y-md">
    <header>
        <h2 class="font-headline-md text-headline-md text-error">Delete Account</h2>
        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
            Once your account is deleted, all of its resources and data will be permanently deleted. Please download any data you wish to retain first.
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >Delete Account</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-lg">
            @csrf
            @method('delete')

            <h2 class="font-headline-md text-headline-md text-on-surface">Are you sure you want to delete your account?</h2>

            <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">
                This action is permanent. Please enter your password to confirm.
            </p>

            <div class="mt-lg">
                <x-input-label for="password" value="Password" class="sr-only" />
                <x-text-input id="password" name="password" type="password" placeholder="Password" />
                <x-input-error :messages="$errors->userDeletion->get('password')" />
            </div>

            <div class="mt-lg flex justify-end gap-sm">
                <x-secondary-button x-on:click="$dispatch('close')">Cancel</x-secondary-button>
                <x-danger-button>Delete Account</x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
