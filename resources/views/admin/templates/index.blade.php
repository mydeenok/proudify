@php use Illuminate\Support\Facades\Storage; @endphp

<x-layouts.admin-shell title="Templates">
    <x-page-header
        title="Template Library"
        description="Manage and configure credential designs across the platform."
    >
        <x-slot:actions>
            <form method="GET" class="hidden md:block relative">
                <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant text-[20px]">search</span>
                <input
                    type="search"
                    name="search"
                    value="{{ request('search') }}"
                    placeholder="Search templates..."
                    class="form-input pl-10 w-64"
                />
            </form>
            <a href="{{ route('admin.templates.create') }}" class="btn-primary h-11">
                <span class="material-symbols-outlined text-[20px]">add</span>
                New Template
            </a>
        </x-slot:actions>
    </x-page-header>

    <div class="flex items-center justify-between mb-lg">
        <p class="font-body-md text-body-md text-on-surface-variant">{{ $templates->total() }} templates</p>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-gutter">
        @foreach ($templates as $template)
            <article class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden flex flex-col bento-hover min-h-[300px]">
                {{-- Preview --}}
                <div class="h-40 bg-surface-container-low relative border-b border-outline-variant p-md flex items-center justify-center">
                    <div class="absolute top-sm right-sm">
                        @if ($template->is_active)
                            <span class="inline-flex items-center px-2 py-1 rounded-full font-label-sm text-[10px] uppercase tracking-wider border border-emerald-500/20 bg-emerald-500/10 text-emerald-600 backdrop-blur-sm">Active</span>
                        @else
                            <span class="inline-flex items-center px-2 py-1 rounded-full font-label-sm text-[10px] uppercase tracking-wider border border-gray-500/20 bg-gray-500/10 text-gray-500 backdrop-blur-sm">Draft</span>
                        @endif
                    </div>
                    @if ($template->thumbnail_path)
                        <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="max-h-full max-w-full drop-shadow-md rounded-lg object-contain">
                    @else
                        <div class="w-full h-full border-2 border-dashed border-outline-variant rounded-lg flex items-center justify-center bg-surface-container-lowest">
                            <span class="material-symbols-outlined text-outline-variant text-4xl">image</span>
                        </div>
                    @endif
                </div>

                {{-- Info --}}
                <div class="p-lg flex-1 flex flex-col border-b border-outline-variant/60">
                    <h3 class="font-headline-md text-headline-md text-on-surface truncate mb-xs">{{ $template->name }}</h3>
                    <p class="font-body-sm text-body-sm text-on-surface-variant line-clamp-2 flex-1">{{ $template->category ?? 'Uncategorized' }}</p>
                </div>

                {{-- Footer: Live toggle + actions --}}
                <div class="px-lg py-md flex items-center justify-between">
                    <x-toggle-switch
                        :checked="$template->is_active"
                        :action="route('admin.templates.toggle-status', $template)"
                        :label="$template->is_active ? 'Live' : 'Draft'"
                    />

                    <div class="flex gap-xs">
                        <a href="{{ route('admin.templates.builder', $template) }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-container-low text-on-surface-variant hover:text-primary transition-colors" title="Edit in Builder">
                            <span class="material-symbols-outlined text-[20px]">edit</span>
                        </a>
                        <a href="{{ route('admin.templates.edit', $template) }}" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-surface-container-low text-on-surface-variant hover:text-on-surface transition-colors" title="Settings">
                            <span class="material-symbols-outlined text-[20px]">settings</span>
                        </a>
                        <form method="POST" action="{{ route('admin.templates.destroy', $template) }}" onsubmit="return confirm('Delete this template?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-error/10 text-on-surface-variant hover:text-error transition-colors" title="Delete">
                                <span class="material-symbols-outlined text-[20px]">delete</span>
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @endforeach

        {{-- Blank template card --}}
        <a href="{{ route('admin.templates.create') }}" class="bg-surface-container border-2 border-dashed border-outline-variant rounded-xl flex flex-col items-center justify-center p-lg hover:bg-surface-container-high transition-colors min-h-[300px] bento-hover">
            <div class="w-16 h-16 rounded-full bg-primary-container/10 flex items-center justify-center mb-md text-primary">
                <span class="material-symbols-outlined text-4xl">add_circle</span>
            </div>
            <h3 class="font-headline-md text-headline-md text-on-surface mb-xs">Blank Template</h3>
            <p class="font-body-sm text-body-sm text-on-surface-variant text-center">Start from scratch using the visual builder.</p>
        </a>
    </div>

    @if ($templates->hasPages())
        <div class="mt-lg">
            {{ $templates->links() }}
        </div>
    @endif
</x-layouts.admin-shell>
