<div>
    <div class="card-surface shadow-card-sm overflow-hidden">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-md border-b border-outline-variant px-md py-sm bg-floor">
            <div class="flex flex-wrap items-center gap-sm min-w-0">
                <h3 class="font-label-md text-label-md font-semibold text-on-surface shrink-0">Inbox</h3>
                <span class="inline-flex items-center rounded-full bg-surface-variant px-2.5 py-0.5 text-xs font-semibold text-on-surface-variant border border-outline-variant">
                    {{ $contactRequests->total() }} {{ Str::plural('message', $contactRequests->total()) }}
                </span>
                @if ($openCount > 0)
                    <span class="status-pill-pending">{{ $openCount }} open</span>
                @endif
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-sm w-full lg:w-auto">
                <div class="w-full sm:w-64">
                    <x-search-input
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search name, email, subject…"
                        class="h-10 text-body-sm"
                    />
                </div>
                <x-filter-select name="status" wire:model.live="status" class="h-10 min-w-[160px]">
                    <option value="">All statuses</option>
                    <option value="open">Open</option>
                    <option value="in_progress">In progress</option>
                    <option value="closed">Closed</option>
                </x-filter-select>
            </div>
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="data-table w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr>
                        <th class="min-w-[220px]">From</th>
                        <th>Subject</th>
                        <th class="w-32">Status</th>
                        <th class="w-40">Received</th>
                        <th class="text-right pr-lg w-28">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($contactRequests as $contactRequest)
                        @php
                            $badgeStatus = match ($contactRequest->status) {
                                'open' => 'pending',
                                'in_progress' => 'active',
                                'closed' => 'expired',
                                default => 'default',
                            };
                        @endphp
                        <tr class="group" wire:key="contact-{{ $contactRequest->id }}">
                            <td>
                                <div class="flex flex-col gap-0.5 min-w-0">
                                    <span class="font-label-md text-label-md text-on-surface font-semibold truncate">{{ $contactRequest->name }}</span>
                                    <span class="text-on-surface-variant text-[11px] truncate">{{ $contactRequest->email }}</span>
                                    @if ($contactRequest->organization)
                                        <span class="text-on-surface-variant text-[11px] truncate">{{ $contactRequest->organization }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <a
                                    href="{{ route('admin.contact-requests.show', $contactRequest) }}"
                                    wire:navigate
                                    class="font-body-md text-body-md text-on-surface hover:text-primary line-clamp-2"
                                >
                                    {{ $contactRequest->subject }}
                                </a>
                            </td>
                            <td>
                                <x-status-badge :status="$badgeStatus">
                                    {{ str_replace('_', ' ', ucfirst($contactRequest->status)) }}
                                </x-status-badge>
                            </td>
                            <td class="text-on-surface-variant whitespace-nowrap">
                                {{ $contactRequest->created_at->format('d M Y') }}
                                <div class="text-[11px]">{{ $contactRequest->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="text-right pr-lg">
                                <a href="{{ route('admin.contact-requests.show', $contactRequest) }}" wire:navigate class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary inline-flex" title="View">
                                    <span class="material-symbols-outlined text-[20px]">visibility</span>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-xl text-center font-body-md text-body-md text-on-surface-variant">
                                No contact requests {{ ($search !== '' || $status !== '') ? 'match these filters' : 'yet' }}.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($contactRequests->hasPages())
            <div class="px-md py-sm border-t border-outline-variant">
                {{ $contactRequests->links() }}
            </div>
        @endif
    </div>
</div>
