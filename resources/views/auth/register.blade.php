<x-layouts.guest title="Register">
    <x-slot:header>
        <h1 class="font-headline-lg text-headline-lg text-on-surface">Create your account</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Set up your organization and start issuing.</p>
    </x-slot:header>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-md">
        @csrf

        <div class="grid grid-cols-2 gap-md">
            <div class="flex flex-col gap-base">
                <x-input-label for="first_name" value="First name" />
                <x-text-input id="first_name" name="first_name" type="text" required autofocus autocomplete="given-name" :value="old('first_name')" placeholder="Jane" />
                <x-input-error :messages="$errors->get('first_name')" />
            </div>
            <div class="flex flex-col gap-base">
                <x-input-label for="last_name" value="Last name" />
                <x-text-input id="last_name" name="last_name" type="text" required autocomplete="family-name" :value="old('last_name')" placeholder="Doe" />
                <x-input-error :messages="$errors->get('last_name')" />
            </div>
        </div>

        <div class="flex flex-col gap-base">
            <x-input-label for="organization_name" value="Organization" />
            <x-icon-input id="organization_name" name="organization_name" type="text" icon="business" required :value="old('organization_name')" placeholder="Acme University" />
            <x-input-error :messages="$errors->get('organization_name')" />
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
            <div class="flex flex-col gap-base">
                <x-input-label for="email" value="Work email" />
                <x-icon-input id="email" name="email" type="email" icon="mail" required autocomplete="username" :value="old('email')" placeholder="jane@company.com" />
                <x-input-error :messages="$errors->get('email')" />
            </div>
            <div class="flex flex-col gap-base">
                <x-input-label for="phone" value="Phone" />
                <x-icon-input id="phone" name="phone" type="tel" icon="phone" required :value="old('phone')" placeholder="+91 98765 43210" />
                <x-input-error :messages="$errors->get('phone')" />
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-md">
            <div class="flex flex-col gap-base">
                <x-input-label for="password" value="Password" />
                <x-icon-input id="password" name="password" type="password" icon="lock" required autocomplete="new-password" placeholder="Min. 8 characters" />
                <x-input-error :messages="$errors->get('password')" />
            </div>
            <div class="flex flex-col gap-base">
                <x-input-label for="password_confirmation" value="Confirm" />
                <x-icon-input id="password_confirmation" name="password_confirmation" type="password" icon="lock" required autocomplete="new-password" placeholder="••••••••" />
                <x-input-error :messages="$errors->get('password_confirmation')" />
            </div>
        </div>

        <x-primary-button class="btn-primary w-full h-11 justify-center">
            Create account
            <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
        </x-primary-button>
    </form>

    <p class="text-center lg:text-left font-body-md text-body-md text-on-surface-variant">
        Already registered?
        <a href="{{ route('login') }}" class="text-primary font-semibold hover:underline">Sign in</a>
    </p>
</x-layouts.guest>
