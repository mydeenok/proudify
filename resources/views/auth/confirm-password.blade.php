<x-layouts.guest title="Confirm password">
    <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-xs">Confirm your password</h1>
    <p class="font-body-md text-body-md text-on-surface-variant mb-xl">This is a secure area. Please confirm your password before continuing.</p>

    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-md">
        @csrf

        <div>
            <x-input-label for="password" value="Password" />
            <x-text-input id="password" name="password" type="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <x-primary-button class="w-full">Confirm</x-primary-button>
    </form>
</x-layouts.guest>
