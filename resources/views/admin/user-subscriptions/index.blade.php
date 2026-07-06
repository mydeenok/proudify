<x-layouts.admin-shell title="User Subscriptions">
    <x-page-header
        title="User Subscriptions"
        description="Manage active revenue, monitor plan usage, and handle operational overrides."
    >
        <x-slot:actions>
            <form method="GET" class="flex gap-sm">
                <div class="w-full md:w-64">
                    <x-search-input
                        name="search"
                        placeholder="Search users or plans…"
                        value="{{ request('search') }}"
                        class="h-11"
                    />
                </div>
                <button type="submit" class="btn-secondary h-11 px-md">
                    <span class="material-symbols-outlined text-[18px]">filter_list</span>
                    Filter
                </button>
            </form>
        </x-slot:actions>
    </x-page-header>

    <div class="card-surface bento-shadow overflow-hidden flex flex-col">
        <div class="p-md md:p-lg border-b border-outline-variant flex justify-between items-center bg-floor">
            <h3 class="font-headline-md text-headline-md font-semibold text-on-surface">Subscription Roster</h3>
            <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $subscriptions->total() }} total</span>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr>
                        <th class="w-[250px]">User</th>
                        <th>Plan</th>
                        <th>Status</th>
                        <th>Usage</th>
                        <th>Renewal</th>
                        <th class="text-right pr-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        @php
                            $usagePercent = $subscription->certificates_limit > 0
                                ? min(100, round(($subscription->certificates_used / $subscription->certificates_limit) * 100))
                                : 0;
                            $badgeStatus = match ($subscription->payment_status) {
                                'completed' => 'active',
                                'pending' => 'pending',
                                'failed' => 'failed',
                                'refunded' => 'expired',
                                default => 'default',
                            };
                        @endphp
                        <tr class="group">
                            <td>
                                <div class="flex items-center gap-sm">
                                    <div class="w-8 h-8 rounded-full bg-surface-container-high text-primary flex items-center justify-center font-bold font-label-sm shrink-0 border border-outline-variant">
                                        {{ strtoupper(substr($subscription->user->organization_name, 0, 2)) }}
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="font-label-md text-label-md text-on-surface font-semibold">{{ $subscription->user->organization_name }}</span>
                                        <span class="text-on-surface-variant text-[11px]">{{ $subscription->user->email }}</span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="font-medium text-on-surface">{{ $subscription->subscription->name }}</span>
                            </td>
                            <td>
                                <x-status-badge :status="$badgeStatus">
                                    {{ ucfirst($subscription->payment_status) }}
                                </x-status-badge>
                                @if (! $subscription->is_active)
                                    <x-status-badge status="expired" class="ml-1">Cancelled</x-status-badge>
                                @endif
                            </td>
                            <td>
                                <div class="flex flex-col gap-1 w-full max-w-[140px]">
                                    <div class="flex justify-between text-[10px] {{ $usagePercent >= 90 ? 'text-error' : 'text-on-surface-variant' }}">
                                        <span>{{ $subscription->certificates_used }} / {{ $subscription->certificates_limit }}</span>
                                        <span>{{ $usagePercent }}%</span>
                                    </div>
                                    <div class="h-1.5 w-full bg-secondary-container rounded-full overflow-hidden">
                                        <div class="h-full {{ $usagePercent >= 90 ? 'bg-error' : 'bg-primary' }} rounded-full" style="width: {{ $usagePercent }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="flex flex-col">
                                    <span class="text-on-surface">{{ $subscription->end_date->format('d M Y') }}</span>
                                </div>
                            </td>
                            <td class="text-right pr-lg">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ route('admin.user-subscriptions.edit', $subscription) }}" class="h-8 px-3 text-[11px] font-medium text-on-surface bg-surface border border-outline-variant rounded-md hover:bg-surface-variant transition-colors">Edit</a>
                                    @if ($subscription->is_active)
                                        <form method="PATCH" action="{{ route('admin.user-subscriptions.cancel', $subscription) }}" class="inline" onsubmit="return confirm('Cancel this subscription?');">
                                            @csrf
                                            <button type="submit" class="h-8 px-3 text-[11px] font-medium text-error bg-surface border border-error/30 rounded-md hover:bg-error/5 transition-colors">Cancel</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-md py-xl text-center font-body-md text-body-md text-on-surface-variant">No subscriptions yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-outline-variant p-md flex items-center justify-between bg-floor">
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                Showing {{ $subscriptions->firstItem() ?? 0 }} to {{ $subscriptions->lastItem() ?? 0 }} of {{ $subscriptions->total() }} entries
            </span>
            <div>
                {{ $subscriptions->links() }}
            </div>
        </div>
    </div>
</x-layouts.admin-shell>
