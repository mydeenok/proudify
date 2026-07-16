@php
$statusLabels = [
    'mapping' => ['Mapping columns', 'bg-surface-variant text-on-surface-variant'],
    'processing' => ['Processing', 'bg-tertiary-container/30 text-on-tertiary-container'],
    'completed' => ['Completed', 'bg-emerald-500/10 text-emerald-700'],
    'completed_with_errors' => ['Completed with errors', 'bg-amber-500/10 text-amber-700'],
    'failed' => ['Failed', 'bg-error-container text-on-error-container'],
];
@endphp

<nav class="flex items-center gap-xs font-label-sm text-label-sm text-on-surface-variant mb-xs">
    <a href="{{ route('admin.dashboard') }}" class="hover:text-primary transition-colors">Home</a>
    <span class="material-symbols-outlined text-[14px]">chevron_right</span>
    <a href="{{ route('bulk-upload.history') }}" class="hover:text-primary transition-colors">Bulk Uploads</a>
    <span class="material-symbols-outlined text-[14px]">chevron_right</span>
    <span class="text-on-surface">Batch #{{ $batch->id }}</span>
</nav>

<div
    x-data="{
        status: '{{ $batch->status }}',
        totalRows: {{ $batch->total_rows }},
        processedRows: {{ $batch->processed_rows }},
        succeededRows: {{ $batch->succeeded_rows }},
        failedRows: {{ $batch->failed_rows }},
        progressPercent: {{ $batch->progressPercent() }},
        finished: {{ $batch->isFinished() ? 'true' : 'false' }},
        errorReportAvailable: {{ $batch->error_report_path ? 'true' : 'false' }},
        statusLabels: @js($statusLabels),
        poll() {
            if (this.finished) return;
            fetch('{{ route('bulk-upload.status-data', $batch) }}')
                .then(response => response.json())
                .then(data => {
                    Object.assign(this, data);
                    if (!this.finished) setTimeout(() => this.poll(), 2000);
                });
        },
    }"
    x-init="poll()"
>
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div>
            <div class="flex items-center gap-sm mb-xs">
                <h1 class="font-headline-xl text-headline-xl text-on-surface">{{ $batch->template?->name ?? 'Deleted template' }}</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full font-label-sm text-label-sm" :class="statusLabels[status]?.[1]" x-text="statusLabels[status]?.[0] ?? status"></span>
            </div>
            <p class="font-body-md text-body-md text-on-surface-variant">{{ $batch->original_filename }} &middot; {{ $batch->user?->organization_name }}</p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <a href="{{ route('bulk-upload.history') }}" class="btn-secondary h-11">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Bulk Uploads
            </a>
            <a x-show="errorReportAvailable" x-cloak href="{{ route('bulk-upload.error-report', $batch) }}" class="btn-primary h-11">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Download Error Report
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-gutter mb-gutter">
        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Rows processed</span>
                <div class="w-8 h-8 rounded-full bg-surface-container-low flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined text-[18px]" :class="{ 'animate-spin': !finished }">progress_activity</span>
                </div>
            </div>
            <div class="font-headline-xl text-headline-xl text-on-surface" x-text="`${processedRows} / ${totalRows}`"></div>
            <div class="font-label-sm text-label-sm text-on-surface-variant mt-xs" x-text="`${progressPercent}% complete`"></div>
        </div>

        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Succeeded</span>
                <div class="w-8 h-8 rounded-full bg-emerald-500/10 flex items-center justify-center text-emerald-600">
                    <span class="material-symbols-outlined text-[18px]">check_circle</span>
                </div>
            </div>
            <div class="font-headline-xl text-headline-xl text-emerald-700" x-text="succeededRows"></div>
        </div>

        <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
            <div class="flex justify-between items-start mb-md">
                <span class="font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider">Failed</span>
                <div class="w-8 h-8 rounded-full bg-error-container flex items-center justify-center text-error">
                    <span class="material-symbols-outlined text-[18px]">error</span>
                </div>
            </div>
            <div class="font-headline-xl text-headline-xl text-error" x-text="failedRows"></div>
        </div>
    </div>

    <div class="bg-surface border border-outline-variant rounded-xl p-lg shadow-[0px_4px_12px_rgba(0,0,0,0.02)]">
        <div class="flex justify-between items-center mb-xs">
            <span class="font-label-sm text-label-sm text-on-surface-variant">Progress</span>
            <span class="font-label-sm text-label-sm text-on-surface" x-text="`${progressPercent}%`"></span>
        </div>
        <div class="h-2 bg-secondary-container rounded-full overflow-hidden">
            <div class="h-full bg-primary-container transition-all duration-500" :style="`width: ${progressPercent}%`"></div>
        </div>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-md" x-show="!finished">This page updates automatically — no need to refresh.</p>
    </div>
</div>
