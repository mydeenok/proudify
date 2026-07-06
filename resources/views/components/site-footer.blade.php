<footer class="bg-surface-container-highest border-t border-outline-variant mt-auto">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-xl px-margin py-2xl max-w-7xl mx-auto">
        <div class="flex flex-col gap-sm col-span-1">
            <x-application-logo variant="brand" href="/" />
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-sm">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
        </div>
        <div class="flex flex-col gap-sm">
            <h4 class="font-label-sm text-label-sm font-bold text-on-surface mb-xs uppercase tracking-wider">Product</h4>
            <a href="{{ url('/#features') }}" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Features</a>
            <a href="{{ route('pricing') }}" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Pricing</a>
        </div>
        <div class="flex flex-col gap-sm">
            <h4 class="font-label-sm text-label-sm font-bold text-on-surface mb-xs uppercase tracking-wider">Legal</h4>
            <a href="#" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Privacy Policy</a>
            <a href="#" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Terms of Service</a>
        </div>
        <div class="flex flex-col gap-sm">
            <h4 class="font-label-sm text-label-sm font-bold text-on-surface mb-xs uppercase tracking-wider">Support</h4>
            <a href="#" class="font-body-sm text-body-sm text-on-surface-variant hover:text-primary transition-colors">Contact</a>
        </div>
    </div>
</footer>
