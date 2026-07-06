@php
use Illuminate\Support\Facades\Storage;

$registryStatus = function ($certificate) {
    if ($certificate->status === 'revoked') {
        return 'revoked';
    }
    if ($certificate->image_generation_status === 'failed') {
        return 'failed';
    }
    if (! $certificate->pdf_path) {
        return 'pending';
    }
    if ($certificate->isExpired()) {
        return 'expired';
    }

    return 'verified';
};
@endphp

<x-layouts.admin-shell title="Certificates">
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-md mb-xl">
        <div class="flex flex-col gap-xs">
            <nav class="flex items-center gap-xs font-label-sm text-label-sm text-on-surface-variant">
                <a href="{{ route('admin.dashboard') }}" class="hover:text-primary transition-colors">Home</a>
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
                <span class="text-on-surface">Certificates Management</span>
            </nav>
            <h1 class="font-headline-xl text-headline-xl text-on-surface">Certificates Registry</h1>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Manage, verify, and issue credentials across all educational cohorts.</p>
        </div>
        <div class="flex items-center gap-sm shrink-0">
            <button type="submit" form="bulk-download-form" id="btn-download-selected" class="btn-secondary h-11 opacity-50" disabled>
                <span class="material-symbols-outlined text-[20px]">download</span>
                Download Selected (ZIP)
            </button>
            @if (Route::has('admin.bulk-upload.create'))
                <a href="{{ route('admin.bulk-upload.create') }}" class="btn-secondary h-11">
                    <span class="material-symbols-outlined text-[20px]">upload_file</span>
                    Bulk Upload
                </a>
            @endif
            <a href="{{ route('templates.index') }}" class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                Create Certificate
            </a>
        </div>
    </div>

    <form method="GET" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm flex flex-wrap gap-md items-center mb-gutter">
        <div class="relative flex-1 min-w-[280px]">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">search</span>
            <input
                type="search"
                name="search"
                value="{{ request('search') }}"
                placeholder="Search by recipient name, ID, or issuer..."
                class="form-input pl-10 shadow-sm"
            />
        </div>

        <div class="flex flex-wrap items-center gap-sm">
            <x-filter-select name="status" class="min-w-[150px]">
                <option value="">All Statuses</option>
                <option value="active" @selected(request('status') === 'active')>Active / Verified</option>
                <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                <option value="expired" @selected(request('status') === 'expired')>Expired</option>
                <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                <option value="failed" @selected(request('status') === 'failed')>Failed</option>
            </x-filter-select>

            <x-filter-select name="template_id" class="min-w-[160px]">
                <option value="">All Templates</option>
                @foreach ($templates as $template)
                    <option value="{{ $template->id }}" @selected((string) request('template_id') === (string) $template->id)>{{ $template->name }}</option>
                @endforeach
            </x-filter-select>

            <x-filter-select name="period" class="min-w-[150px]">
                <option value="">All time</option>
                <option value="7" @selected(request('period') === '7')>Last 7 Days</option>
                <option value="30" @selected(request('period') === '30')>Last 30 Days</option>
            </x-filter-select>

            <button type="submit" class="h-[44px] w-[44px] flex items-center justify-center bg-surface border border-outline-variant rounded-lg text-on-surface-variant hover:bg-surface-container-high transition-all shadow-sm" title="Apply filters">
                <span class="material-symbols-outlined">tune</span>
            </button>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.certificates.bulk-download') }}" id="bulk-download-form">
        @csrf
    </form>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant h-12">
                        <th class="px-md py-sm w-12">
                            <input type="checkbox" id="select-all" aria-label="Select all rows" class="w-4 h-4 rounded border-outline-variant text-primary-container focus:ring-primary-container cursor-pointer bg-surface">
                        </th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-20">Preview</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider min-w-[200px]">Certificate Title</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider min-w-[160px]">Recipient</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider min-w-[160px]">Issuer / Cohort</th>
                        <th class="px-sm py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-28">Status</th>
                        <th class="px-md py-sm font-label-sm text-label-sm text-on-surface-variant uppercase tracking-wider w-44 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline-variant">
                    @forelse ($certificates as $certificate)
                        @php $status = $registryStatus($certificate); @endphp
                        <tr @class([
                            'h-14 hover:bg-surface-container transition-colors group',
                            'bg-error-container/10' => $status === 'failed',
                        ])>
                            <td class="px-md py-xs">
                                <input type="checkbox" name="certificate_ids[]" value="{{ $certificate->id }}" form="bulk-download-form" class="row-checkbox w-4 h-4 rounded border-outline-variant text-primary-container focus:ring-primary-container cursor-pointer bg-surface">
                            </td>
                            <td class="px-sm py-xs">
                                @if ($certificate->image_path && Storage::disk('public')->exists($certificate->image_path))
                                    <img src="{{ Storage::url($certificate->image_path) }}" alt="" class="w-14 h-9 object-cover rounded border border-outline-variant shadow-sm bg-surface {{ $status === 'failed' ? 'opacity-50 grayscale' : '' }}">
                                @elseif ($certificate->template?->thumbnail_path)
                                    <img src="{{ Storage::url($certificate->template->thumbnail_path) }}" alt="" class="w-14 h-9 object-cover rounded border border-outline-variant shadow-sm bg-surface">
                                @elseif (! $certificate->pdf_path)
                                    <div class="w-14 h-9 rounded border border-dashed border-outline-variant bg-surface flex items-center justify-center">
                                        <span class="material-symbols-outlined text-outline-variant text-[16px]">draft</span>
                                    </div>
                                @else
                                    <div class="w-14 h-9 rounded border border-outline-variant bg-surface-variant flex items-center justify-center">
                                        <span class="material-symbols-outlined text-on-surface-variant text-[16px]">image</span>
                                    </div>
                                @endif
                            </td>
                            <td class="px-sm py-xs">
                                <div class="font-label-md text-label-md text-on-surface truncate max-w-[250px]">{{ $certificate->title }}</div>
                                @if ($status === 'failed')
                                    <div class="font-body-sm text-body-sm text-error truncate">Generation failed</div>
                                @else
                                    <div class="font-body-sm text-body-sm text-on-surface-variant truncate">ID: {{ $certificate->verification_code }}</div>
                                @endif
                            </td>
                            <td class="px-sm py-xs">
                                <div class="font-body-md text-body-md text-on-surface">{{ $certificate->recipient_name }}</div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant">{{ $certificate->recipient_email }}</div>
                            </td>
                            <td class="px-sm py-xs">
                                <div class="font-body-md text-body-md text-on-surface">{{ $certificate->user->organization_name }}</div>
                                <div class="font-body-sm text-body-sm text-on-surface-variant">{{ $certificate->template?->category ?? $certificate->template?->name ?? '—' }}</div>
                            </td>
                            <td class="px-sm py-xs">
                                <x-registry-status-badge :status="$status" />
                            </td>
                            <td class="px-md py-xs text-right">
                                <div class="flex items-center justify-end gap-xs opacity-0 group-hover:opacity-100 transition-opacity">
                                    <a href="{{ route('certificates.show', $certificate) }}" class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="View">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <a href="{{ route('certificates.verify', ['uuid' => $certificate->uuid, 'code' => $certificate->verification_code]) }}" target="_blank" class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="Verify">
                                        <span class="material-symbols-outlined text-[20px]">verified</span>
                                    </a>
                                    @if ($certificate->status === 'active')
                                        <button type="button" class="p-1 rounded hover:bg-error-container text-on-surface-variant hover:text-error transition-colors" title="Revoke" onclick="document.getElementById('revoke-form-{{ $certificate->id }}').classList.toggle('hidden')">
                                            <span class="material-symbols-outlined text-[20px]">block</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if ($certificate->status === 'active')
                            <tr id="revoke-form-{{ $certificate->id }}" class="hidden bg-error-container/5">
                                <td colspan="7" class="px-md py-sm">
                                    <form method="POST" action="{{ route('admin.certificates.revoke', $certificate) }}" class="flex items-center gap-sm max-w-2xl">
                                        @csrf
                                        @method('PATCH')
                                        <input type="text" name="reason" required maxlength="255" placeholder="Reason for revocation…" class="form-input flex-1 h-10">
                                        <button type="submit" class="btn-primary h-10 px-md bg-error hover:bg-error/90 shadow-none whitespace-nowrap">Confirm Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="px-md py-xl text-center font-body-md text-body-md text-on-surface-variant">No certificates found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="h-14 border-t border-outline-variant bg-surface flex items-center justify-between px-md">
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                Showing {{ $certificates->firstItem() ?? 0 }} to {{ $certificates->lastItem() ?? 0 }} of {{ number_format($certificates->total()) }} entries
            </span>
            <div>
                {{ $certificates->links() }}
            </div>
        </div>
    </section>

    <script>
        const selectAll = document.getElementById('select-all');
        const downloadBtn = document.getElementById('btn-download-selected');
        const rowCheckboxes = () => document.querySelectorAll('.row-checkbox');

        const syncDownloadButton = () => {
            const anyChecked = [...rowCheckboxes()].some((cb) => cb.checked);
            if (downloadBtn) {
                downloadBtn.disabled = !anyChecked;
                downloadBtn.classList.toggle('opacity-50', !anyChecked);
                downloadBtn.classList.toggle('cursor-not-allowed', !anyChecked);
            }
        };

        selectAll?.addEventListener('change', (event) => {
            rowCheckboxes().forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
            syncDownloadButton();
        });

        rowCheckboxes().forEach((checkbox) => {
            checkbox.addEventListener('change', syncDownloadButton);
        });
    </script>
</x-layouts.admin-shell>
