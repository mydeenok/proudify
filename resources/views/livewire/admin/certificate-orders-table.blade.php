<div>
    <div class="card-surface shadow-card overflow-hidden flex flex-col">
        <div class="p-md md:p-lg border-b border-outline-variant flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md bg-floor">
            <div class="flex items-center gap-md">
                <h3 class="font-headline-md text-headline-md font-semibold text-on-surface">Certificate Orders</h3>
                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $orders->total() }} total</span>
            </div>
            <div class="flex items-center gap-sm">
                <select wire:model.live="status" class="h-11 rounded-lg border border-outline-variant bg-surface px-md font-body-sm text-body-sm text-on-surface">
                    <option value="all">All statuses</option>
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                    <option value="refunded">Refunded</option>
                </select>
                <div class="w-full sm:w-64">
                    <x-search-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search users…"
                        class="h-11"
                    />
                </div>
            </div>
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="data-table w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr>
                        <th class="w-[220px]">User</th>
                        <th>Template</th>
                        <th>Type / Qty</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th class="text-right pr-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        @php
                            $needsAttention = $order->status === 'paid' && $order->type === 'single' && ! $order->certificate_id;
                            $badgeStatus = match ($order->status) {
                                'paid' => 'active',
                                'pending' => 'pending',
                                'failed', 'cancelled', 'expired' => 'failed',
                                'refunded' => 'expired',
                                default => 'default',
                            };
                        @endphp
                        <tr class="group {{ $needsAttention ? 'bg-error/5' : '' }}" wire:key="order-{{ $order->id }}">
                            <td>
                                <div class="flex flex-col">
                                    <span class="font-label-md text-label-md text-on-surface font-semibold">{{ $order->user->organization_name }}</span>
                                    <span class="text-on-surface-variant text-[11px]">{{ $order->user->email }}</span>
                                </div>
                            </td>
                            <td>{{ $order->template->name }}</td>
                            <td>{{ ucfirst($order->type) }} &middot; {{ $order->quantity }}</td>
                            <td>₹{{ number_format($order->total_amount, 2) }}</td>
                            <td>
                                <x-status-badge :status="$badgeStatus">{{ ucfirst($order->status) }}</x-status-badge>
                                @if ($needsAttention)
                                    <p class="text-error text-[11px] mt-1">Paid, not issued</p>
                                @endif
                            </td>
                            <td>{{ $order->created_at->format('d M Y') }}</td>
                            <td class="text-right pr-lg">
                                @if ($order->status === 'paid')
                                    <form method="POST" action="{{ route('admin.certificate-orders.refund', $order) }}" class="inline" onsubmit="return confirm('Refund ₹{{ number_format($order->total_amount, 2) }} to {{ $order->user->email }}? This cannot be undone.');">
                                        @csrf
                                        <button type="submit" class="h-8 px-3 text-[11px] font-medium text-error bg-surface border border-error/30 rounded-md hover:bg-error/5 transition-colors">Refund</button>
                                    </form>
                                @elseif ($order->status === 'refunded')
                                    <span class="text-on-surface-variant text-[11px]">Refunded {{ $order->refunded_at->format('d M Y') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-md py-xl text-center font-body-md text-body-md text-on-surface-variant">No orders yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-outline-variant p-md flex items-center justify-between bg-floor">
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                Showing {{ $orders->firstItem() ?? 0 }} to {{ $orders->lastItem() ?? 0 }} of {{ $orders->total() }} entries
            </span>
            <div>
                {{ $orders->links() }}
            </div>
        </div>
    </div>
</div>
