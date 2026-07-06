<x-layouts.guest title="Awaiting approval">
    <div class="text-center">
        <div class="w-16 h-16 rounded-full bg-tertiary-container/20 text-tertiary flex items-center justify-center mx-auto mb-lg">
            <span class="material-symbols-outlined text-[32px]">hourglass_top</span>
        </div>

        <h1 class="font-headline-lg text-headline-lg font-bold text-on-surface mb-xs">Your email is verified</h1>
        <p class="font-body-md text-body-md text-on-surface-variant mb-xl">
            An administrator now needs to approve your organization before you can log in. We'll email you as soon as that happens.
        </p>

        <a href="{{ route('login') }}" class="font-label-md text-label-md text-primary font-semibold hover:underline">
            Back to login
        </a>
    </div>
</x-layouts.guest>
