<x-layouts.guest title="Reset password">
    <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-xs">Reset your password</h1>
    <p class="font-body-md text-body-md text-on-surface-variant mb-xl">Choose a new password for your account.</p>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-md">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Email" />
            <x-text-input id="email" name="email" type="email" required autofocus autocomplete="username" :value="old('email', $request->email)" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="New password" />
            <x-text-input id="password" name="password" type="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-primary-button class="w-full">Reset Password</x-primary-button>
    </form>
</x-layouts.guest>
