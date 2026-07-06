<section>
    <header>
        <h2 class="font-headline-md text-headline-md text-on-surface">Profile Information</h2>
        <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Update your name, organization, and phone number.</p>
    </header>

    <form method="post" action="{{ route('profile.update') }}" class="mt-lg space-y-md">
        @csrf
        @method('patch')

        <div class="grid grid-cols-2 gap-md">
            <div>
                <x-input-label for="first_name" value="First name" />
                <x-text-input id="first_name" name="first_name" type="text" :value="old('first_name', $user->first_name)" required autofocus autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" />
            </div>
            <div>
                <x-input-label for="last_name" value="Last name" />
                <x-text-input id="last_name" name="last_name" type="text" :value="old('last_name', $user->last_name)" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div>
            <x-input-label for="organization_name" value="Organization name" />
            <x-text-input id="organization_name" name="organization_name" type="text" :value="old('organization_name', $user->organization_name)" required />
            <x-input-error :messages="$errors->get('organization_name')" />
        </div>

        <div>
            <x-input-label for="phone" value="Phone number" />
            <x-text-input id="phone" name="phone" type="tel" :value="old('phone', $user->phone)" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>

        <div>
            <x-input-label value="Email" />
            <p class="font-body-md text-body-md text-on-surface-variant">{{ $user->email }}</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-xs">Email cannot be changed after registration.</p>
        </div>

        <div class="flex items-center gap-md">
            <x-primary-button>Save</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p x-data="{ show: true }" x-show="show" x-transition x-init="setTimeout(() => show = false, 2000)" class="font-body-sm text-body-sm text-on-surface-variant">
                    Saved.
                </p>
            @endif
        </div>
    </form>
</section>
