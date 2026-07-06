@php
$paymentStatusMap = [
    'completed' => 'active',
    'pending' => 'pending',
    'failed' => 'failed',
    'refunded' => 'expired',
];
$certUsed = $active?->certificates_used ?? 0;
$certLimit = $active?->certificates_limit ?? 0;
$certPercent = $certLimit > 0 ? round(($certUsed / $certLimit) * 100) : 0;
$circumference = 2 * M_PI * 45;
$strokeOffset = $circumference - ($circumference * min($certPercent, 100) / 100);
@endphp

<x-layouts.user-shell title="My Subscription">
    <header class="text-center mb-xl w-full max-w-[800px] mx-auto">
        <h1 class="font-headline-xl text-headline-xl text-on-surface mb-sm">My Subscription</h1>
        <p class="font-body-md text-body-md text-on-surface-variant">Manage your current plan, view billing history, and upgrade your account.</p>
    </header>

    <div class="w-full max-w-[800px] mx-auto grid grid-cols-1 md:grid-cols-12 gap-gutter mb-xl">
        @if ($active)
            <div class="md:col-span-12 card-surface p-lg bento-shadow relative overflow-hidden">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-xl relative z-10">
                    <div>
                        <div class="flex items-center gap-sm mb-xs">
                            <span class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant">Current Plan</span>
                            <x-status-badge status="active">Active</x-status-badge>
                        </div>
                        <h2 class="font-headline-xl text-headline-xl text-on-surface">{{ $active->subscription->name }}</h2>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-sm">
                            Renews {{ $active->end_date->format('M d, Y') }} &middot; {{ ucfirst($active->billing_period) }} billing
                        </p>
                    </div>
                    <div class="flex items-center gap-lg">
                        <div class="relative w-[120px] h-[120px] flex items-center justify-center">
                            <svg class="w-full h-full transform -rotate-90" viewBox="0 0 100 100">
                                <circle cx="50" cy="50" fill="none" r="45" stroke="#e5e7eb" stroke-width="8"></circle>
                                <circle cx="50" cy="50" fill="none" r="45" stroke="#d92727" stroke-dasharray="{{ $circumference }}" stroke-dashoffset="{{ $strokeOffset }}" stroke-linecap="round" stroke-width="8"></circle>
                            </svg>
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="font-headline-lg text-headline-lg text-on-surface">{{ $certUsed }}</span>
                                <span class="font-label-sm text-label-sm text-on-surface-variant">/{{ $certLimit }} certs</span>
                            </div>
                        </div>
                        <div class="flex flex-col gap-sm">
                            <div class="p-md bg-surface-container-lowest rounded-lg border border-outline-variant">
                                <p class="font-body-sm text-body-sm text-on-surface-variant">Recipients</p>
                                <p class="font-label-md text-label-md text-on-surface">{{ $active->users_used }} / {{ $active->users_limit }}</p>
                            </div>
                            <a href="{{ route('pricing') }}" class="btn-primary text-center">
                                Upgrade Plan
                                <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @else
            <div class="md:col-span-12 card-surface p-lg bento-shadow">
                <p class="font-body-md text-body-md text-on-surface-variant mb-md">You don't have an active subscription.</p>
                <a href="{{ route('pricing') }}" class="btn-primary">
                    View Plans
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </a>
            </div>
        @endif

        <div class="md:col-span-12 card-surface p-lg bento-shadow-sm">
            <h3 class="font-headline-md text-headline-md text-on-surface mb-md">Billing History</h3>
            <div class="overflow-x-auto w-full">
                <table class="data-table w-full text-left border-collapse">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($subscriptions as $subscription)
                            <tr class="h-14">
                                <td>{{ $subscription->start_date->format('M d, Y') }}</td>
                                <td>
                                    {{ $subscription->subscription->name }}
                                    <span class="text-on-surface-variant">&middot; {{ ucfirst($subscription->billing_period) }}</span>
                                </td>
                                <td class="font-medium">
                                    {{ $subscription->amount_paid > 0 ? $subscription->currency.' '.number_format($subscription->amount_paid, 2) : 'Free' }}
                                </td>
                                <td>
                                    <x-status-badge :status="$paymentStatusMap[$subscription->payment_status] ?? 'default'">
                                        {{ ucfirst($subscription->payment_status) }}
                                    </x-status-badge>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-xl text-center font-body-md text-body-md text-on-surface-variant">
                                    No subscription history yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($subscriptions->hasPages())
                <div class="mt-lg border-t border-outline-variant pt-md">
                    {{ $subscriptions->links() }}
                </div>
            @endif
        </div>
    </div>
</x-layouts.user-shell>
