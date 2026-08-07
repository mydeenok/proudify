@php
use Illuminate\Support\Facades\Storage;

$isAdmin = auth()->user()->isAdmin();
$shell = $isAdmin ? 'layouts.admin-shell' : 'layouts.user-shell';
@endphp

<x-dynamic-component :component="$shell" title="Templates">
    @if ($isAdmin)
        <div class="mb-md">
            <a href="{{ route('admin.certificates.index') }}" class="inline-flex items-center gap-xs font-label-md text-label-md text-on-surface-variant hover:text-primary transition-colors">
                <span class="material-symbols-outlined text-[18px]">arrow_back</span>
                Back to Certificates Registry
            </a>
        </div>
    @endif

    <x-page-header
        title="Template Library"
        :description="$isAdmin ? 'Select a template to issue a new certificate.' : 'Choose a design, then fill in the recipient details.'"
        class="mb-xl"
    >
        <x-slot:actions>
            @if ($categories->isNotEmpty())
                <div class="flex flex-wrap gap-sm">
                    <a href="{{ route('templates.index') }}" class="px-md py-xs rounded-lg font-label-sm text-label-sm {{ request('category') ? 'bg-surface text-on-surface-variant border border-outline-variant hover:bg-surface-variant' : 'bg-primary-container text-on-primary-container border border-transparent' }} transition-colors">
                        All
                    </a>
                    @foreach ($categories as $category)
                        <a href="{{ route('templates.index', ['category' => $category]) }}" class="px-md py-xs rounded-lg font-label-sm text-label-sm {{ request('category') === $category ? 'bg-primary-container text-on-primary-container border border-transparent' : 'bg-surface text-on-surface-variant border border-outline-variant hover:bg-surface-variant' }} transition-colors">
                            {{ $category }}
                        </a>
                    @endforeach
                </div>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-md">
        @forelse ($templates as $template)
            <article class="group flex flex-col bg-surface-container-lowest border border-outline-variant rounded-lg overflow-hidden transition-[box-shadow,border-color] duration-200 hover:border-outline hover:shadow-card-sm">
                <a
                    href="{{ route('certificates.create', ['template' => $template->id]) }}"
                    class="relative block bg-surface-container-low aspect-[1000/707] p-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-primary focus-visible:ring-inset"
                >
                    @if ($template->thumbnail_path)
                        <img
                            src="{{ Storage::url($template->thumbnail_path) }}"
                            alt="{{ $template->name }}"
                            class="h-full w-full object-contain drop-shadow-sm rounded-sm bg-white"
                        >
                    @else
                        <div class="h-full w-full flex flex-col items-center justify-center gap-xs rounded-md border border-dashed border-outline-variant bg-surface-container-lowest">
                            <span class="material-symbols-outlined text-outline-variant text-[28px]">workspace_premium</span>
                            <span class="font-label-sm text-[10px] text-on-surface-variant">No preview</span>
                        </div>
                    @endif
                </a>

                <div class="flex flex-1 flex-col gap-sm p-sm border-t border-outline-variant">
                    <div class="min-w-0">
                        <div class="flex items-start justify-between gap-xs">
                            <h2 class="font-label-md text-label-md font-semibold text-on-surface truncate">{{ $template->name }}</h2>
                            @if ($template->category)
                                <span class="shrink-0 inline-flex items-center px-1.5 py-0.5 rounded bg-surface-container font-label-sm text-[10px] text-on-surface-variant">
                                    {{ $template->category }}
                                </span>
                            @endif
                        </div>
                        <p class="mt-0.5 font-label-sm text-[11px] text-on-surface-variant">
                            {{ $template->orientation === 'portrait' ? 'Portrait' : 'Landscape' }}
                            · {{ strtoupper($template->page_format ?? 'a4') }}
                        </p>
                    </div>

                    <a
                        href="{{ route('certificates.create', ['template' => $template->id]) }}"
                        class="btn-primary w-full justify-center h-9 text-label-sm px-sm"
                    >
                        <span class="material-symbols-outlined text-[16px]">edit_document</span>
                        Use
                    </a>
                </div>
            </article>
        @empty
            <div class="col-span-full flex flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-low px-lg py-2xl text-center">
                <span class="material-symbols-outlined text-outline-variant text-[48px] mb-md">folder_open</span>
                <p class="font-headline-md text-headline-md text-on-surface mb-xs">No templates available yet</p>
                <p class="font-body-md text-body-md text-on-surface-variant max-w-md">
                    {{ $isAdmin ? 'Publish a design from the Visual Builder to list it here.' : 'Check back soon — new designs are added by your organization.' }}
                </p>
            </div>
        @endforelse
    </div>
</x-dynamic-component>
