<x-layouts.contextual-shell title="Batch Status">
    <div class="max-w-2xl mx-auto"
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
            <template x-if="!finished">
                <div class="w-16 h-16 rounded-full bg-tertiary-container/20 text-tertiary flex items-center justify-center mx-auto mb-md">
                    <span class="material-symbols-outlined text-[32px] animate-spin">progress_activity</span>
                </div>
            </template>
            <template x-if="finished">
                <div class="w-16 h-16 rounded-full bg-emerald-500/10 text-emerald-600 flex items-center justify-center mx-auto mb-md">
                    <span class="material-symbols-outlined text-[32px]">check_circle</span>
                </div>
            </template>

            <h1 class="font-headline-xl text-headline-xl text-on-surface mb-xs" x-text="finished ? 'Batch Complete' : 'Issuing Certificates…'"></h1>
            <p class="font-body-md text-body-md text-on-surface-variant">This page updates automatically — no need to refresh.</p>
        </div>

        <div class="card-surface p-lg bento-shadow">
            <div class="flex justify-between mb-xs">
                <span class="font-label-sm text-label-sm text-on-surface-variant" x-text="`${processedRows} / ${totalRows} rows processed`"></span>
                <span class="font-label-sm text-label-sm text-on-surface" x-text="`${progressPercent}%`"></span>
            </div>
            <div class="h-2 bg-secondary-container rounded-full overflow-hidden mb-lg">
                <div class="h-full bg-primary-container transition-all duration-500" :style="`width: ${progressPercent}%`"></div>
            </div>

            <div class="grid grid-cols-2 gap-md">
                <div class="text-center p-md bg-surface-container-lowest rounded-lg border border-outline-variant">
                    <p class="font-headline-md text-headline-md text-emerald-700" x-text="succeededRows"></p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Succeeded</p>
                </div>
                <div class="text-center p-md bg-surface-container-lowest rounded-lg border border-outline-variant">
                    <p class="font-headline-md text-headline-md text-error" x-text="failedRows"></p>
                    <p class="font-label-sm text-label-sm text-on-surface-variant">Failed</p>
                </div>
            </div>
        </div>

        <div class="mt-lg flex justify-center gap-md">
            <a href="{{ auth()->user()->isAdmin() ? route('admin.dashboard') : route('certificates.index') }}" class="btn-primary">
                {{ auth()->user()->isAdmin() ? 'Back to Dashboard' : 'View My Certificates' }}
            </a>
            <a x-show="errorReportAvailable" x-cloak href="{{ route('bulk-upload.error-report', $batch) }}" class="btn-secondary">
                <span class="material-symbols-outlined text-[18px]">download</span>
                Download Error Report
            </a>
        </div>
    </div>
</x-layouts.contextual-shell>
