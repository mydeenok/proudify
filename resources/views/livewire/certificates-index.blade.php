<div x-data="{ mode: $persist('list').as('certificates-view-mode') }">
    <div id="certificates-view" class="flex flex-col md:flex-row md:items-center justify-between gap-lg mb-lg border-b border-outline-variant pb-md">
        <div class="flex gap-lg">
            <button
                type="button"
                wire:click="setStatus('')"
                @class([
                    'nav-tab-active' => $status === '',
                    'nav-tab' => $status !== '',
                ])
            >All</button>
            <button
                type="button"
                wire:click="setStatus('active')"
                @class([
                    'nav-tab-active' => $status === 'active',
                    'nav-tab' => $status !== 'active',
                ])
            >Active</button>
            <button
                type="button"
                wire:click="setStatus('expired')"
                @class([
                    'nav-tab-active' => $status === 'expired',
                    'nav-tab' => $status !== 'expired',
                ])
            >Expired</button>
        </div>

        <div class="flex items-center gap-md w-full md:w-auto">
            <x-search-input
                wire:model.live.debounce.300ms="search"
                placeholder="Search certificates..."
                class="w-full md:w-64"
            />
            <div class="flex bg-surface-variant p-base rounded-lg border border-outline-variant shrink-0">
                <button type="button" @click="mode = 'list'" class="p-xs rounded flex items-center justify-center" :class="mode === 'list' ? 'bg-surface shadow-sm text-primary' : ''" title="List view">
                    <span class="material-symbols-outlined text-[20px]">view_list</span>
                </button>
                <button type="button" @click="mode = 'grid'" class="p-xs text-on-surface-variant hover:text-on-surface transition-colors flex items-center justify-center" :class="mode === 'grid' ? 'bg-surface shadow-sm text-primary' : ''" title="Grid view">
                    <span class="material-symbols-outlined text-[20px]">grid_view</span>
                </button>
            </div>
        </div>
    </div>

    @php
        use Illuminate\Support\Facades\Storage;
        use Illuminate\Support\Str;
    @endphp

    <div x-show="mode === 'list'" class="card-surface shadow-card-sm overflow-hidden" wire:loading.class="opacity-60">
        <div class="overflow-x-auto">
            <table class="data-table w-full text-left border-collapse min-w-[800px]">
                <thead>
                    <tr>
                        <th class="w-[100px]">Thumbnail</th>
                        <th>Title</th>
                        <th>Recipient</th>
                        <th>Issue Date</th>
                        <th>Status</th>
                        <th class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($certificates as $certificate)
                        @php $displayStatus = $certificate->displayStatus(); @endphp
                        <tr class="group" wire:key="certificate-list-{{ $certificate->id }}">
                            <td>
                                <div class="w-16 h-12 bg-surface-variant rounded border border-outline-variant overflow-hidden flex items-center justify-center">
                                    @if ($certificate->image_path && Storage::disk('local')->exists($certificate->image_path))
                                        <img src="{{ route('certificates.image', $certificate) }}" alt="{{ $certificate->title }}" class="w-full h-full object-cover">
                                    @elseif ($certificate->template?->thumbnail_path)
                                        <img src="{{ Storage::url($certificate->template->thumbnail_path) }}" alt="{{ $certificate->title }}" class="w-full h-full object-cover">
                                    @else
                                        <span class="material-symbols-outlined text-on-surface-variant text-[20px]">image</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <p class="font-label-md text-label-md text-on-surface">{{ $certificate->title }}</p>
                                @if ($certificate->description)
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ Str::limit($certificate->description, 40) }}</p>
                                @endif
                            </td>
                            <td class="font-body-md text-body-md">{{ $certificate->recipient_name }}</td>
                            <td class="text-on-surface-variant">{{ $certificate->date_of_issue->format('M d, Y') }}</td>
                            <td>
                                <x-status-badge :status="$displayStatus">{{ ucfirst($displayStatus) }}</x-status-badge>
                            </td>
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-sm opacity-0 group-hover:opacity-100 transition-opacity">
                                    @if ($certificate->pdf_path)
                                        <a href="{{ route('certificates.download', $certificate) }}" class="p-xs text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center rounded-lg hover:bg-surface-variant" title="Download">
                                            <span class="material-symbols-outlined text-[20px]">download</span>
                                        </a>
                                    @endif
                                    <a href="{{ route('certificates.show', $certificate) }}" wire:navigate class="p-xs text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center rounded-lg hover:bg-surface-variant" title="View">
                                        <span class="material-symbols-outlined text-[20px]">visibility</span>
                                    </a>
                                    <a href="{{ route('certificates.show', $certificate) }}" wire:navigate class="p-xs text-on-surface-variant hover:text-primary transition-colors flex items-center justify-center rounded-lg hover:bg-surface-variant" title="More">
                                        <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-xl text-center font-body-md text-body-md text-on-surface-variant">
                                No certificates issued yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($certificates->hasPages())
            <div class="px-md py-sm border-t border-outline-variant bg-floor flex items-center justify-between">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Showing {{ $certificates->firstItem() }} to {{ $certificates->lastItem() }} of {{ $certificates->total() }} certificates
                </p>
                <div>{{ $certificates->links() }}</div>
            </div>
        @endif
    </div>

    <div x-show="mode === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-gutter" wire:loading.class="opacity-60">
        @forelse ($certificates as $certificate)
            @php $displayStatus = $certificate->displayStatus(); @endphp
            <div class="card-surface shadow-card-sm overflow-hidden group flex flex-col" wire:key="certificate-grid-{{ $certificate->id }}">
                <div class="relative aspect-[4/3] bg-surface-container-low overflow-hidden">
                    @if ($certificate->image_path && Storage::disk('local')->exists($certificate->image_path))
                        <img src="{{ route('certificates.image', $certificate) }}" alt="{{ $certificate->title }}" class="relative z-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @elseif ($certificate->template?->thumbnail_path)
                        <img src="{{ Storage::url($certificate->template->thumbnail_path) }}" alt="{{ $certificate->title }}" class="relative z-0 w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @else
                        <div class="relative z-0 w-full h-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-surface-variant text-[48px]">workspace_premium</span>
                        </div>
                    @endif
                    <div class="absolute top-sm right-sm z-10 pointer-events-none">
                        <x-status-badge :status="$displayStatus">{{ ucfirst($displayStatus) }}</x-status-badge>
                    </div>
                </div>
                <div class="p-md flex flex-col flex-grow">
                    <h3 class="font-headline-md text-headline-md text-on-surface truncate">{{ $certificate->title }}</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">{{ $certificate->recipient_name }}</p>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs">{{ $certificate->date_of_issue->format('M d, Y') }}</p>
                    <div class="flex items-center gap-sm mt-md pt-md border-t border-outline-variant">
                        @if ($certificate->pdf_path)
                            <a href="{{ route('certificates.download', $certificate) }}" class="btn-secondary text-sm py-xs px-sm flex-1 justify-center">
                                <span class="material-symbols-outlined text-[16px]">download</span>
                                Download
                            </a>
                        @endif
                        <a href="{{ route('certificates.show', $certificate) }}" wire:navigate class="btn-primary text-sm py-xs px-sm flex-1 justify-center">
                            View
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <p class="col-span-full text-center py-2xl font-body-md text-body-md text-on-surface-variant">
                No certificates issued yet.
            </p>
        @endforelse

        @if ($certificates->hasPages())
            <div class="col-span-full px-md py-sm border border-outline-variant rounded-lg bg-floor flex items-center justify-between">
                <p class="font-body-sm text-body-sm text-on-surface-variant">
                    Showing {{ $certificates->firstItem() }} to {{ $certificates->lastItem() }} of {{ $certificates->total() }} certificates
                </p>
                <div>{{ $certificates->links() }}</div>
            </div>
        @endif
    </div>
</div>
