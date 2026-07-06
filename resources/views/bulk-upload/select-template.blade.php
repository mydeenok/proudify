@php use Illuminate\Support\Facades\Storage; @endphp

<x-layouts.user-shell title="Bulk Issue Certificates">
    <div class="max-w-3xl mx-auto">
        <div class="mb-xl text-center">
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Bulk Issue Certificates</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Upload a CSV file to issue multiple certificates at once. Follow the 4-step process below.</p>
        </div>

        <x-bulk-upload-stepper current="Select Template" />

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-gutter">
            @forelse ($templates as $template)
                <a href="{{ route('bulk-upload.create', ['template' => $template->id]) }}" class="group relative card-surface bento-shadow-sm overflow-hidden hover:bento-shadow transition-shadow duration-300 flex flex-col h-[280px]">
                    <div class="relative flex-1 bg-surface-container-low overflow-hidden">
                        @if ($template->thumbnail_path)
                            <img src="{{ Storage::url($template->thumbnail_path) }}" alt="{{ $template->name }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                        @else
                            <div class="w-full h-full flex items-center justify-center">
                                <span class="material-symbols-outlined text-on-surface-variant text-[48px]">workspace_premium</span>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-on-background/60 opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex items-center justify-center backdrop-blur-sm">
                            <span class="btn-primary pointer-events-none">
                                <span class="material-symbols-outlined text-[18px]">check_circle</span>
                                Select Template
                            </span>
                        </div>
                    </div>
                    <div class="p-md bg-surface border-t border-outline-variant">
                        <p class="font-label-md text-label-md text-on-surface font-semibold truncate">{{ $template->name }}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $template->category ?? 'General' }}</p>
                    </div>
                </a>
            @empty
                <p class="col-span-full text-center py-2xl font-body-md text-body-md text-on-surface-variant">
                    No templates available yet.
                </p>
            @endforelse
        </div>
    </div>
</x-layouts.user-shell>
