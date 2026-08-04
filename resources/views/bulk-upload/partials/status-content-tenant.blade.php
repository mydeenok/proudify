<div class="max-w-xl mx-auto"
    x-data="{
        status: '{{ $batch->status }}',
        totalRows: {{ $batch->total_rows }},
        processedRows: {{ $batch->processed_rows }},
        succeededRows: {{ $batch->succeeded_rows }},
        failedRows: {{ $batch->failed_rows }},
        progressPercent: {{ $batch->progressPercent() }},
        finished: {{ $batch->isFinished() ? 'true' : 'false' }},
        errorReportAvailable: {{ $batch->error_report_path ? 'true' : 'false' }},
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
    <div class="text-center mb-xl">
        <div class="relative w-24 h-24 mx-auto mb-lg">
            <div class="absolute inset-0 rounded-full transition-[background] duration-700 ease-out"
                :style="`background: conic-gradient(${finished ? '#059669' : '#b40012'} ${progressPercent * 3.6}deg, #eef0f3 0deg)`">
            </div>
            <div class="absolute inset-[7px] rounded-full bg-surface flex items-center justify-center">
                <template x-if="!finished">
                    <span class="font-headline-md text-headline-md text-on-surface tabular-nums" x-text="`${progressPercent}%`"></span>
                </template>
                <template x-if="finished">
                    <span class="material-symbols-outlined text-[36px] text-emerald-600">check_circle</span>
                </template>
            </div>
        </div>

        <h1 class="font-headline-xl text-headline-xl text-on-surface mb-xs flex items-center justify-center gap-sm">
            <span x-text="finished ? 'Batch complete' : 'Issuing certificates…'"></span>
            <span x-show="!finished" class="relative flex h-2.5 w-2.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-primary-container opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-primary-container"></span>
            </span>
        </h1>
        <p class="font-body-sm text-body-sm text-on-surface-variant" x-show="!finished">This page updates automatically — no need to refresh.</p>
        <p class="font-body-sm text-body-sm text-on-surface-variant" x-show="finished" x-cloak x-text="`${succeededRows} of ${totalRows} certificates issued${failedRows > 0 ? `, ${failedRows} failed` : ''}.`"></p>
    </div>

    <div class="card-surface p-lg shadow-card">
        <div class="flex justify-between items-center mb-xs">
            <span class="font-label-sm text-label-sm text-on-surface-variant" x-text="`${processedRows} / ${totalRows} rows processed`"></span>
            <span class="font-label-sm text-label-sm text-on-surface font-semibold" x-text="`${progressPercent}%`"></span>
        </div>
        <div class="h-2 bg-secondary-container rounded-full overflow-hidden mb-lg">
            <div class="h-full rounded-full transition-all duration-500" :class="finished && failedRows === 0 ? 'bg-emerald-500' : 'bg-primary-container'" :style="`width: ${progressPercent}%`"></div>
        </div>

        <div class="grid grid-cols-2 gap-md">
            <div class="flex items-center gap-sm p-md bg-emerald-50/60 rounded-lg border border-emerald-200">
                <span class="material-symbols-outlined text-[22px] text-emerald-600 shrink-0">check_circle</span>
                <div class="text-left">
                    <p class="font-headline-md text-headline-md text-emerald-700 leading-none" x-text="succeededRows"></p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Succeeded</p>
                </div>
            </div>
            <div class="flex items-center gap-sm p-md rounded-lg border transition-colors"
                :class="failedRows > 0 ? 'bg-error/5 border-error/30' : 'bg-surface-container-lowest border-outline-variant'">
                <span class="material-symbols-outlined text-[22px] shrink-0" :class="failedRows > 0 ? 'text-error' : 'text-on-surface-variant/40'">error</span>
                <div class="text-left">
                    <p class="font-headline-md text-headline-md leading-none" :class="failedRows > 0 ? 'text-error' : 'text-on-surface-variant'" x-text="failedRows"></p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Failed</p>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-lg flex flex-wrap justify-center gap-md">
        <a href="{{ route('bulk-upload.history') }}" class="btn-secondary">
            <span class="material-symbols-outlined text-[18px]">history</span>
            Back to Bulk Uploads
        </a>
        <a href="{{ route('certificates.index') }}" class="btn-primary">
            <span class="material-symbols-outlined text-[18px]">workspace_premium</span>
            View My Certificates
        </a>
        <a x-show="errorReportAvailable" x-cloak href="{{ route('bulk-upload.error-report', $batch) }}" class="btn-secondary">
            <span class="material-symbols-outlined text-[18px]">download</span>
            Download Error Report
        </a>
    </div>
</div>
