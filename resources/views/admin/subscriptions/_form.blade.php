@php $plan = $plan ?? null; @endphp

<div class="grid grid-cols-1 lg:grid-cols-3 gap-lg">
    <div class="lg:col-span-2 space-y-lg">
        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm space-y-md">
            <div>
                <x-input-label for="name" value="Plan name" />
                <x-text-input id="name" name="name" type="text" required :value="old('name', $plan?->name)" />
                <x-input-error :messages="$errors->get('name')" />
            </div>
            <div>
                <x-input-label for="description" value="Description" />
                <textarea id="description" name="description" rows="3" class="w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-md py-sm font-body-md text-body-md text-on-surface focus:border-primary focus:ring-4 focus:ring-primary/10 outline-none">{{ old('description', $plan?->description) }}</textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Limits</h3>
            <div class="grid grid-cols-2 gap-md">
                <div>
                    <x-input-label for="certificates_per_month" value="Certificates / month" />
                    <x-text-input id="certificates_per_month" name="certificates_per_month" type="number" min="0" required :value="old('certificates_per_month', $plan?->certificates_per_month ?? 0)" />
                    <x-input-error :messages="$errors->get('certificates_per_month')" />
                </div>
                <div>
                    <x-input-label for="certificates_per_year" value="Certificates / year" />
                    <x-text-input id="certificates_per_year" name="certificates_per_year" type="number" min="0" required :value="old('certificates_per_year', $plan?->certificates_per_year ?? 0)" />
                    <x-input-error :messages="$errors->get('certificates_per_year')" />
                </div>
                <div>
                    <x-input-label for="users_per_month" value="Recipients / month" />
                    <x-text-input id="users_per_month" name="users_per_month" type="number" min="0" required :value="old('users_per_month', $plan?->users_per_month ?? 0)" />
                    <x-input-error :messages="$errors->get('users_per_month')" />
                </div>
                <div>
                    <x-input-label for="users_per_year" value="Recipients / year" />
                    <x-text-input id="users_per_year" name="users_per_year" type="number" min="0" required :value="old('users_per_year', $plan?->users_per_year ?? 0)" />
                    <x-input-error :messages="$errors->get('users_per_year')" />
                </div>
            </div>
        </div>

        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Pricing</h3>
            <div class="grid grid-cols-2 gap-md">
                <div>
                    <x-input-label for="cost_month_inr" value="Monthly (INR)" />
                    <x-text-input id="cost_month_inr" name="cost_month_inr" type="number" step="0.01" min="0" required :value="old('cost_month_inr', $plan?->cost_month_inr ?? 0)" />
                    <x-input-error :messages="$errors->get('cost_month_inr')" />
                </div>
                <div>
                    <x-input-label for="cost_year_inr" value="Yearly (INR)" />
                    <x-text-input id="cost_year_inr" name="cost_year_inr" type="number" step="0.01" min="0" required :value="old('cost_year_inr', $plan?->cost_year_inr ?? 0)" />
                    <x-input-error :messages="$errors->get('cost_year_inr')" />
                </div>
                <div>
                    <x-input-label for="cost_month_usd" value="Monthly (USD)" />
                    <x-text-input id="cost_month_usd" name="cost_month_usd" type="number" step="0.01" min="0" required :value="old('cost_month_usd', $plan?->cost_month_usd ?? 0)" />
                    <x-input-error :messages="$errors->get('cost_month_usd')" />
                </div>
                <div>
                    <x-input-label for="cost_year_usd" value="Yearly (USD)" />
                    <x-text-input id="cost_year_usd" name="cost_year_usd" type="number" step="0.01" min="0" required :value="old('cost_year_usd', $plan?->cost_year_usd ?? 0)" />
                    <x-input-error :messages="$errors->get('cost_year_usd')" />
                </div>
            </div>
        </div>
    </div>

    <div class="space-y-lg">
        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm space-y-md">
            <label class="flex items-center gap-sm">
                <input type="checkbox" name="is_default_free_plan" value="1" @checked(old('is_default_free_plan', $plan?->is_default_free_plan)) class="rounded border-outline-variant text-primary focus:ring-primary">
                <span class="font-body-sm text-body-sm text-on-surface">Default free plan</span>
            </label>
            <p class="font-body-sm text-body-sm text-on-surface-variant/70">Only one plan can be the default free plan — setting this unsets any other.</p>

            <label class="flex items-center gap-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan?->is_active ?? true)) class="rounded border-outline-variant text-primary focus:ring-primary">
                <span class="font-body-sm text-body-sm text-on-surface">Active (visible on pricing page)</span>
            </label>
        </div>

        <x-primary-button class="w-full">{{ $plan ? 'Save Changes' : 'Create Plan' }}</x-primary-button>
    </div>
</div>
