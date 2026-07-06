<x-layouts.guest title="Register">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Create your account</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Join the premium certification platform.</p>
    </x-slot:header>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-lg">
        @csrf

        <div class="grid grid-cols-2 gap-md">
            <div class="flex flex-col gap-base">
                <x-input-label for="first_name" value="First name" />
                <x-icon-input id="first_name" name="first_name" type="text" icon="person" required autofocus autocomplete="given-name" :value="old('first_name')" placeholder="Jane" />
                <x-input-error :messages="$errors->get('first_name')" />
            </div>
            <div class="flex flex-col gap-base">
                <x-input-label for="last_name" value="Last name" />
                <x-text-input id="last_name" name="last_name" type="text" required autocomplete="family-name" :value="old('last_name')" placeholder="Doe" />
                <x-input-error :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="organization_name" value="Organization name" />
            <x-icon-input id="organization_name" name="organization_name" type="text" icon="business" required :value="old('organization_name')" placeholder="Acme University" />
            <x-input-error :messages="$errors->get('organization_name')" />
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="email" value="Work email" />
            <x-icon-input id="email" name="email" type="email" icon="mail" required autocomplete="username" :value="old('email')" placeholder="jane@company.com" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="phone" value="Phone number" />
            <x-icon-input id="phone" name="phone" type="tel" icon="phone" required :value="old('phone')" placeholder="+1 555 000 0000" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="password" value="Password" />
            <x-icon-input id="password" name="password" type="password" icon="lock" required autocomplete="new-password" placeholder="••••••••" />
            <p class="font-body-sm text-body-sm text-on-surface-variant">Must be at least 8 characters long.</p>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-icon-input id="password_confirmation" name="password_confirmation" type="password" icon="lock" required autocomplete="new-password" placeholder="••••••••" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button class="w-full">
            Create Account
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </x-primary-button>
    </form>

    <p class="text-center font-body-md text-body-md text-on-surface-variant pt-md border-t border-outline-variant/50">
        Already have an account?
        <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Log in</a>
    </p>

    <x-slot:footer>
        <div class="mt-xl flex justify-center gap-lg opacity-60">
            <div class="flex items-center gap-xs">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-variation-settings: 'FILL' 1;">shield</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Secure</span>
            </div>
            <div class="flex items-center gap-xs">
                <span class="material-symbols-outlined text-on-surface-variant" style="font-variation-settings: 'FILL' 1;">verified</span>
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Verified</span>
            </div>
        </div>
    </x-slot:footer>
</x-layouts.guest>
