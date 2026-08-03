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

    <div class="mb-xl flex flex-col md:flex-row md:items-end justify-between gap-md">
        <div>
            <h1 class="font-headline-xl text-headline-xl text-on-surface mb-xs">Template Library</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">
                {{ $isAdmin ? 'Select a template to issue a new certificate.' : 'Choose from our premium collection of verifiable credentials.' }}
            </p>
        </div>

        @if ($categories->isNotEmpty())
            <div class="flex flex-wrap gap-sm">
                <a href="{{ route('templates.index') }}" class="px-md py-xs rounded-full font-label-sm text-label-sm {{ request('category') ? 'bg-surface text-on-surface-variant border border-outline-variant hover:bg-surface-variant hover:text-on-surface' : 'bg-primary-container text-on-primary-container shadow-sm border border-transparent' }} transition-all">
                    All
                </a>
                @foreach ($categories as $category)
                    <a href="{{ route('templates.index', ['category' => $category]) }}" class="px-md py-xs rounded-full font-label-sm text-label-sm {{ request('category') === $category ? 'bg-primary-container text-on-primary-container shadow-sm border border-transparent' : 'bg-surface text-on-surface-variant border border-outline-variant hover:bg-surface-variant hover:text-on-surface' }} transition-all">
                        {{ $category }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-gutter">
        @forelse ($templates as $template)
            <div class="group relative card-surface shadow-card-sm overflow-hidden hover:shadow-card transition-shadow duration-300 flex flex-col h-[320px]">
                <div class="relative flex-1 bg-surface-container-low overflow-hidden">
                    @if ($template->thumbnail_path)
                        <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                    @else
                        <div class="w-full h-full flex items-center justify-center">
                            <span class="material-symbols-outlined text-on-surface-variant text-[48px]">workspace_premium</span>
                        </div>
                    @endif
                    <div class="absolute inset-0 bg-on-background/80 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex flex-col items-center justify-center gap-md backdrop-blur-sm">
                        <a href="{{ route('certificates.create', ['template' => $template->id]) }}" class="btn-primary">
                            <span class="material-symbols-outlined text-[18px]">edit_document</span>
                            Use Template
                        </a>
                    </div>
                </div>
                <div class="p-md bg-surface border-t border-outline-variant">
                    <h3 class="font-headline-md text-headline-md text-on-surface truncate">{{ $template->name }}</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-base">{{ $template->category ?? 'General' }}</p>
                </div>
            </div>
        @empty
            <p class="col-span-full text-center py-2xl font-body-md text-body-md text-on-surface-variant">
                No templates available yet.
            </p>
        @endforelse
    </div>
</x-dynamic-component>
