<div>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div class="flex flex-col gap-xs">
            <h1 class="font-headline-xl text-headline-xl text-on-surface">Bulk Upload History</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">
                {{ $isAdmin ? 'Every bulk certificate batch issued across all organizations.' : 'Every bulk certificate batch you\'ve uploaded.' }}
            </p>
        </div>
        <a href="{{ $newUploadUrl }}" wire:navigate class="btn-primary h-11 shrink-0">
            <span class="material-symbols-outlined text-[20px]">upload_file</span>
            New Bulk Upload
        </a>
    </div>

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm flex flex-wrap gap-md items-center mb-gutter">
        <x-filter-select name="status" wire:model.live="status" class="min-w-[180px]">
            <option value="">All Statuses</option>
            <option value="mapping">Mapping columns</option>
            <option value="processing">Processing</option>
            <option value="completed">Completed</option>
            <option value="completed_with_errors">Completed with errors</option>
            <option value="failed">Failed</option>
        </x-filter-select>

        @if ($isAdmin)
            <x-filter-select name="user_id" wire:model.live="user_id" class="min-w-[200px]">
                <option value="">All Organizations</option>
                @foreach ($orgUsers as $orgUser)
                    <option value="{{ $orgUser->id }}">{{ $orgUser->organization_name }}</option>
                @endforeach
            </x-filter-select>
        @endif
    </div>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col overflow-hidden" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[900px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant h-12">
                        <th class="px-md py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider min-w-[180px]">Template</th>
                        @if ($isAdmin)
                            <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider min-w-[160px]">Organization</th>
                        @endif
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-40">Uploaded</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-44">Status</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-40">Rows</th>
                        <th class="px-md py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-44 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($batches as $batch)
                        <tr class="h-16 hover:bg-surface-container transition-colors" wire:key="batch-{{ $batch->id }}">
                            <td class="px-md py-xs">
                                <div class="font-label-md text-label-md text-on-surface truncate max-w-[220px]">{{ $batch->template?->name ?? 'Deleted template' }}</div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant truncate max-w-[220px]">{{ $batch->original_filename }}</div>
                            </td>
                            @if ($isAdmin)
                                <td class="px-sm py-xs">
                                    <div class="font-body-md text-body-md text-on-surface">{{ $batch->user?->organization_name ?? '—' }}</div>
                                </td>
                            @endif
                            <td class="px-sm py-xs">
                                <div class="font-body-sm text-body-sm text-on-surface-variant">{{ $batch->created_at->format('d M Y') }}</div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant">{{ $batch->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-sm py-xs">
                                <x-batch-status-badge :status="$batch->status" />
                            </td>
                            <td class="px-sm py-xs">
                                <div class="font-body-sm text-body-sm text-on-surface">
                                    <span class="text-emerald-700 font-semibold">{{ $batch->succeeded_rows }}</span>
                                    /
                                    @if ($batch->failed_rows > 0)
                                        <span class="text-error font-semibold">{{ $batch->failed_rows }}</span>
                                    @else
                                        {{ $batch->failed_rows }}
                                    @endif
                                    / {{ $batch->total_rows }}
                                </div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant">succeeded / failed / total</div>
                            </td>
                            <td class="px-md py-xs text-right">
                                <div class="flex items-center justify-end gap-xs">
                                    @if ($batch->status === 'mapping')
                                        <a href="{{ route('bulk-upload.map-columns', $batch) }}" wire:navigate class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="Continue: map columns">
                                            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                                        </a>
                                    @elseif ($batch->status === 'queued')
                                        <a href="{{ route('bulk-upload.review', $batch) }}" wire:navigate class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="Continue: review and issue">
                                            <span class="material-symbols-outlined text-[20px]">arrow_forward</span>
                                        </a>
                                    @else
                                        <a href="{{ route('bulk-upload.status', $batch) }}" wire:navigate class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="View status">
                                            <span class="material-symbols-outlined text-[20px]">visibility</span>
                                        </a>
                                    @endif
                                    @if ($batch->error_report_path)
                                        <a href="{{ route('bulk-upload.error-report', $batch) }}" class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="Download error report">
                                            <span class="material-symbols-outlined text-[20px]">download</span>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $isAdmin ? 6 : 5 }}" class="px-md py-2xl text-center">
                                <div class="flex flex-col items-center gap-sm text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[40px] text-outline-variant">upload_file</span>
                                    <p class="font-body-md text-body-md">No bulk uploads {{ ($status !== '' || $user_id !== '') ? 'match these filters' : 'yet' }}.</p>
                                    @if ($status === '' && $user_id === '')
                                        <a href="{{ $newUploadUrl }}" wire:navigate class="font-label-md text-label-md text-primary hover:underline">Start your first bulk upload</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($batches->hasPages())
            <div class="px-md py-sm border-t border-outline-variant">
                {{ $batches->links() }}
            </div>
        @endif
    </section>
</div>
