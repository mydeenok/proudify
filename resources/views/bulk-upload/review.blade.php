@php
$shell = auth()->user()->isAdmin() ? 'layouts.admin-shell' : 'layouts.contextual-shell';
@endphp

<x-dynamic-component :component="$shell" title="Review & Issue">
    <div class="max-w-3xl mx-auto">
        <div class="mb-xl text-center">
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Bulk Issue Certificates</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Review the summary before issuing.</p>
        </div>

        <x-bulk-upload-stepper current="Review & Issue" />

        <div class="card-surface p-lg bento-shadow-sm mb-lg">
            <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Batch Summary</h2>
            <dl class="space-y-2">
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Template</dt>
                    <dd class="font-label-md text-label-md text-on-surface">{{ $batch->template->name }}</dd>
                </div>
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Total Rows</dt>
                    <dd class="font-label-md text-label-md text-on-surface">{{ $batch->total_rows }}</dd>
                </div>
                <div class="flex justify-between py-2 border-b border-outline-variant/50">
                    <dt class="font-body-md text-body-md text-on-surface-variant">Ready to Issue</dt>
                    <dd class="font-label-md text-label-md text-emerald-700">{{ $pendingCount }}</dd>
                </div>
                @if ($skippedCount > 0)
                    <div class="flex justify-between py-2 border-b border-outline-variant/50">
                        <dt class="font-body-md text-body-md text-on-surface-variant">Skipped (duplicates)</dt>
                        <dd class="font-label-md text-label-md text-amber-600">{{ $skippedCount }}</dd>
                    </div>
                @endif
                @if ($failedCount > 0)
                    <div class="flex justify-between py-2">
                        <dt class="font-body-md text-body-md text-on-surface-variant">Failed Validation</dt>
                        <dd class="font-label-md text-label-md text-error">{{ $failedCount }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($pendingCount === 0)
            <div class="bg-error/10 text-error rounded-lg px-md py-sm font-body-sm text-body-sm mb-lg">
                No rows are ready to issue. Check your file and column mapping.
            </div>
        @endif

        <form method="POST" action="{{ route('bulk-upload.confirm', $batch) }}" class="flex justify-between items-center">
            @csrf
            <a href="{{ route('bulk-upload.map-columns', $batch) }}" class="btn-secondary">Back</a>
            <button type="submit" @disabled($pendingCount === 0) class="btn-primary disabled:opacity-50 disabled:cursor-not-allowed">
                <span class="material-symbols-outlined text-[18px]">rocket_launch</span>
                Issue {{ $pendingCount }} Certificates
            </button>
        </form>
    </div>
</x-dynamic-component>
