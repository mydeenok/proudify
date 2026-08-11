<x-layouts.admin-shell title="Billing Settings">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface">Billing Settings</h2>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">
            Controls what every tenant pays per certificate, platform-wide.
        </p>
    </div>

    <form method="POST" action="{{ route('admin.billing-settings.update') }}" class="max-w-2xl">
        @csrf
        @method('PATCH')

        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm space-y-md">
            <div>
                <x-input-label for="price_per_certificate_inr" value="Price per certificate (INR)" />
                <x-text-input id="price_per_certificate_inr" name="price_per_certificate_inr" type="number" step="0.01" min="0.01" required :value="old('price_per_certificate_inr', $settings->price_per_certificate_inr)" />
                <x-input-error :messages="$errors->get('price_per_certificate_inr')" />
            </div>

            <div class="grid grid-cols-2 gap-md">
                <div>
                    <x-input-label for="bulk_discount_threshold" value="Bulk discount kicks in above" />
                    <x-text-input id="bulk_discount_threshold" name="bulk_discount_threshold" type="number" min="0" required :value="old('bulk_discount_threshold', $settings->bulk_discount_threshold)" />
                    <x-input-error :messages="$errors->get('bulk_discount_threshold')" />
                    <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-xs">certificates in one issuance</p>
                </div>
                <div>
                    <x-input-label for="bulk_discount_percent" value="Bulk discount" />
                    <x-text-input id="bulk_discount_percent" name="bulk_discount_percent" type="number" step="0.01" min="0" max="100" required :value="old('bulk_discount_percent', $settings->bulk_discount_percent)" />
                    <x-input-error :messages="$errors->get('bulk_discount_percent')" />
                    <p class="font-body-sm text-body-sm text-on-surface-variant/70 mt-xs">% off the whole batch</p>
                </div>
            </div>

            <p class="font-body-sm text-body-sm text-on-surface-variant border-t border-outline-variant pt-md">
                Example: {{ (int) $settings->bulk_discount_threshold + 1 }} certificates at ₹{{ number_format($settings->price_per_certificate_inr, 2) }} each
                = ₹{{ number_format($settings->price_per_certificate_inr * ((int) $settings->bulk_discount_threshold + 1), 2) }},
                minus {{ number_format($settings->bulk_discount_percent, 2) }}% ({{ $settings->updated_at?->diffForHumans() }} ago).
            </p>
        </div>

        <div class="mt-lg">
            <x-primary-button>Save Changes</x-primary-button>
        </div>
    </form>
</x-layouts.admin-shell>
