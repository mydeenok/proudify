<div
    x-data="{
        sync() {
            const anyChecked = [...$el.querySelectorAll('.row-checkbox')].some((cb) => cb.checked);
            const downloadBtn = document.getElementById('btn-download-selected');
            if (downloadBtn) {
                downloadBtn.disabled = !anyChecked;
                downloadBtn.classList.toggle('opacity-50', !anyChecked);
                downloadBtn.classList.toggle('cursor-not-allowed', !anyChecked);
            }
            const all = [...$el.querySelectorAll('.row-checkbox')];
            const selectAll = $refs.selectAll;
            if (selectAll) {
                selectAll.checked = all.length > 0 && all.every((cb) => cb.checked);
                selectAll.indeterminate = anyChecked && !selectAll.checked;
            }
        },
        toggleAll(event) {
            $el.querySelectorAll('.row-checkbox').forEach((checkbox) => {
                checkbox.checked = event.target.checked;
            });
            this.sync();
        },
    }"
    x-on:change="sync()">
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

    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-md shadow-sm flex flex-wrap gap-md items-center mb-gutter">
        <div class="relative flex-1 min-w-[280px]">
            <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none">search</span>
            <input
                type="search"
                wire:model.live.debounce.300ms="search"
                placeholder="Search by recipient name, ID, or issuer..."
                class="form-input pl-10 shadow-sm" />
        </div>

        <div class="flex flex-wrap items-center gap-sm">
            <x-filter-select name="status" wire:model.live="status" class="min-w-[150px]">
                <option value="">All Statuses</option>
                <option value="active">Active / Verified</option>
                <option value="pending">Pending</option>
                <option value="expired">Expired</option>
                <option value="revoked">Revoked</option>
                <option value="failed">Failed</option>
            </x-filter-select>

            <x-filter-select name="template_id" wire:model.live="template_id" class="min-w-[160px]">
                <option value="">All Templates</option>
                @foreach ($templates as $template)
                <option value="{{ $template->id }}">{{ $template->name }}</option>
                @endforeach
            </x-filter-select>

            <x-filter-select name="period" wire:model.live="period" class="min-w-[150px]">
                <option value="">All time</option>
                <option value="7">Last 7 Days</option>
                <option value="30">Last 30 Days</option>
            </x-filter-select>
        </div>
    </div>

    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl shadow-sm flex flex-col overflow-hidden" wire:loading.class="opacity-60">
        <div class="overflow-x-auto" x-ref="table">
            <table class="w-full text-left border-collapse min-w-[1000px]">
                <thead>
                    <tr class="bg-surface-container-low border-b border-outline-variant h-12">
                        <th class="px-md py-sm w-12">
                            <input
                                type="checkbox"
                                x-ref="selectAll"
                                @change="toggleAll($event)"
                                aria-label="Select all rows"
                                class="w-4 h-4 rounded border-outline-variant text-primary-container focus:ring-primary-container cursor-pointer bg-surface">
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
                    <tr
                        wire:key="certificate-{{ $certificate->id }}"
                        @class([ 'h-14 hover:bg-surface-container transition-colors group' , 'bg-error-container/10'=> $status === 'failed',
                        ])
                        >
                        <td class="px-md py-xs">
                            <input
                                type="checkbox"
                                name="certificate_ids[]"
                                value="{{ $certificate->id }}"
                                form="bulk-download-form"
                                class="row-checkbox w-4 h-4 rounded border-outline-variant text-primary-container focus:ring-primary-container cursor-pointer bg-surface">
                        </td>
                        <td class="px-sm py-xs">
                            @if ($certificate->image_path && Storage::disk('local')->exists($certificate->image_path))
                            <img src="{{ route('certificates.image', $certificate) }}" alt="" class="w-14 h-9 object-cover rounded border border-outline-variant shadow-sm bg-surface {{ $status === 'failed' ? 'opacity-50 grayscale' : '' }}">
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
                                <a href="{{ route('certificates.show', $certificate) }}" wire:navigate class="p-1 rounded hover:bg-surface-variant text-on-surface-variant hover:text-primary transition-colors" title="View">
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
                    <tr id="revoke-form-{{ $certificate->id }}" class="hidden bg-error-container/5" wire:key="revoke-{{ $certificate->id }}">
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
</div>