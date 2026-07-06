<x-layouts.admin-shell title="Edit Subscription">
    <div class="mb-xl">
        <h2 class="font-headline-xl text-headline-xl text-on-surface mb-xs">{{ $userSubscription->user->organization_name }}</h2>
        <p class="font-body-md text-body-md text-on-surface-variant">{{ $userSubscription->subscription->name }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-lg max-w-3xl">
        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Adjust Limits</h3>
            <form method="POST" action="{{ route('admin.user-subscriptions.update', $userSubscription) }}" class="space-y-md">
                @csrf
                @method('PATCH')
                <div>
                    <x-input-label for="certificates_limit" value="Certificate limit" />
                    <x-text-input id="certificates_limit" name="certificates_limit" type="number" min="0" required :value="old('certificates_limit', $userSubscription->certificates_limit)" />
                </div>
                <div>
                    <x-input-label for="users_limit" value="Recipient limit" />
                    <x-text-input id="users_limit" name="users_limit" type="number" min="0" required :value="old('users_limit', $userSubscription->users_limit)" />
                </div>
                <x-primary-button>Save Changes</x-primary-button>
            </form>
        </div>

        <div class="bg-surface rounded-xl border border-outline-variant p-lg shadow-sm">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Extend Subscription</h3>
            <form method="POST" action="{{ route('admin.user-subscriptions.extend', $userSubscription) }}" class="space-y-md">
                @csrf
                <div>
                    <x-input-label for="days" value="Extend by (days)" />
                    <x-text-input id="days" name="days" type="number" min="1" max="365" required value="30" />
                </div>
                <x-primary-button>Extend</x-primary-button>
            </form>

            <div class="mt-lg pt-lg border-t border-outline-variant">
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-sm">Current period ends {{ $userSubscription->end_date->format('d M Y') }}</p>
                @if ($userSubscription->is_active)
                    <form method="POST" action="{{ route('admin.user-subscriptions.cancel', $userSubscription) }}" onsubmit="return confirm('Cancel this subscription?');">
                        @csrf
                        @method('PATCH')
                        <x-danger-button>Cancel Subscription</x-danger-button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-layouts.admin-shell>
