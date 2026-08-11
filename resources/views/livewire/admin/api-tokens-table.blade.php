<div>
    <div class="card-surface shadow-card overflow-hidden flex flex-col">
        <div class="p-md md:p-lg border-b border-outline-variant flex flex-col sm:flex-row justify-between items-start sm:items-center gap-md bg-floor">
            <div class="flex items-center gap-md">
                <h3 class="font-headline-md text-headline-md font-semibold text-on-surface">API Keys</h3>
                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $tokens->total() }} total</span>
            </div>
            <div class="w-full sm:w-64">
                <x-search-input
                    wire:model.live.debounce.300ms="search"
                    placeholder="Search key name or tenant…"
                    class="h-11"
                />
            </div>
        </div>

        <div class="overflow-x-auto" wire:loading.class="opacity-60">
            <table class="data-table w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr>
                        <th class="w-[200px]">Tenant</th>
                        <th>Key name</th>
                        <th>Website</th>
                        <th>Calls</th>
                        <th>Last used</th>
                        <th class="text-right pr-lg">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($tokens as $token)
                        @php $lastLog = $token->requestLogs->first(); @endphp
                        <tr class="group" wire:key="token-{{ $token->id }}">
                            <td>
                                @if ($token->tokenable)
                                    <div class="flex flex-col">
                                        <span class="font-label-md text-label-md text-on-surface font-semibold">{{ $token->tokenable->organization_name }}</span>
                                        <span class="text-on-surface-variant text-[11px]">{{ $token->tokenable->email }}</span>
                                    </div>
                                @else
                                    <span class="text-on-surface-variant text-[11px]">Deleted user</span>
                                @endif
                            </td>
                            <td>{{ $token->name }}</td>
                            <td>
                                @if ($token->website_name || $token->website_url)
                                    <div class="flex flex-col">
                                        <span class="text-on-surface">{{ $token->website_name ?? '—' }}</span>
                                        @if ($token->website_url)
                                            <span class="text-on-surface-variant text-[11px] truncate max-w-[160px]">{{ $token->website_url }}</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-on-surface-variant/70">—</span>
                                @endif
                            </td>
                            <td>
                                <span
                                    class="text-on-surface"
                                    @if ($lastLog)
                                        title="Last call: {{ $lastLog->method }} /{{ $lastLog->path }} from {{ $lastLog->ip_address }}{{ $lastLog->origin ? " (origin: {$lastLog->origin}, self-reported)" : '' }} at {{ $lastLog->created_at->format('d M Y H:i') }}"
                                    @endif
                                >{{ $token->request_logs_count }}</span>
                            </td>
                            <td>
                                @if ($token->last_used_at)
                                    {{ $token->last_used_at->diffForHumans() }}
                                @else
                                    <span class="text-on-surface-variant/70">Never used</span>
                                @endif
                            </td>
                            <td class="text-right pr-lg">
                                <button
                                    type="button"
                                    wire:click="revoke({{ $token->id }})"
                                    wire:confirm="Revoke this API key? Anything using it will stop working immediately."
                                    class="h-8 px-3 text-[11px] font-medium text-error bg-surface border border-error/30 rounded-md hover:bg-error/5 transition-colors"
                                >Revoke</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-md py-xl text-center font-body-md text-body-md text-on-surface-variant">No API keys have been generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="border-t border-outline-variant p-md flex items-center justify-between bg-floor">
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                Showing {{ $tokens->firstItem() ?? 0 }} to {{ $tokens->lastItem() ?? 0 }} of {{ $tokens->total() }} entries
            </span>
            <div>
                {{ $tokens->links() }}
            </div>
        </div>
    </div>
</div>
