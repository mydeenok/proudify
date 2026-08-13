<footer class="bg-surface-container-highest border-t border-outline-variant mt-auto">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-xl px-margin py-2xl max-w-[1200px] mx-auto">
        <div class="flex flex-col gap-sm col-span-1">
            <x-application-logo variant="brand" href="/" />
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-sm">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
        <div class="flex flex-col gap-sm">
            <h4 class="font-label-sm text-label-sm font-bold text-on-surface mb-xs uppercase tracking-wider">Product</h4>
            <a href="{{ url('/#features') }}" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Features</a>
            <a href="{{ url('/#templates') }}" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Templates</a>
        </div>
        {{--
            No Privacy Policy / Terms of Service pages exist anywhere in the
            app (no routes, no views) - the "Legal" column here used to link
            both to href="#", a dead link on every page of a product that
            collects recipient personal data and processes payments. A dead
            link implying a policy exists is worse than not showing one;
            removed until real pages are written and can be linked here.
        --}}
        <div class="flex flex-col gap-sm">
            <h4 class="font-label-sm text-label-sm font-bold text-on-surface mb-xs uppercase tracking-wider">Support</h4>
            <a href="{{ route('contact') }}" wire:navigate class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Contact</a>
            {{-- Was shown unconditionally, including to already-authenticated users reading their own dashboard's footer. --}}
            @guest
                <a href="{{ route('login') }}" wire:navigate class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Login</a>
            @endguest
        </div>
    </div>
</footer>
