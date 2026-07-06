@props(['current'])

@php
$steps = ['Select Template', 'Upload Data', 'Map Columns', 'Review & Issue'];
$currentIndex = array_search($current, $steps, true);
$currentIndex = $currentIndex === false ? 0 : $currentIndex;
$progressPercent = count($steps) > 1 ? ($currentIndex / (count($steps) - 1)) * 100 : 0;
@endphp

<div class="mb-2xl max-w-2xl mx-auto">
    <div class="relative flex justify-between items-center px-5">
        {{-- Track line runs through circle centres (h-10 → centre at 20px) --}}
        <div class="absolute left-5 right-5 top-5 h-[2px] -translate-y-1/2 bg-secondary-container z-0"></div>
        <div
            class="absolute left-5 top-5 h-[2px] -translate-y-1/2 bg-primary-container z-0 transition-all duration-500"
            style="width: calc((100% - 2.5rem) * {{ $progressPercent }} / 100);"
        ></div>

        @foreach ($steps as $index => $label)
            @php
                $isComplete = $index < $currentIndex;
                $isCurrent = $index === $currentIndex;
                $isFuture = $index > $currentIndex;
            @endphp
            <div class="relative z-10 flex flex-col items-center gap-xs">
                <div @class([
                    'w-10 h-10 rounded-full flex items-center justify-center font-label-md text-label-md font-bold ring-4 ring-background shrink-0',
                    'bg-primary-container text-on-primary shadow-sm' => $isComplete,
                    'bg-primary-container text-on-primary shadow-md scale-110' => $isCurrent,
                    'bg-surface text-on-surface-variant border-2 border-secondary-container' => $isFuture,
                ])>
                    @if ($isComplete)
                        <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">check</span>
                    @else
                        {{ $index + 1 }}
                    @endif
                </div>
                <span @class([
                    'font-label-sm text-label-sm text-center max-w-[88px] leading-tight',
                    'text-primary font-bold' => $isCurrent,
                    'text-on-surface font-semibold' => $isComplete,
                    'text-on-surface-variant' => $isFuture,
                ])>{{ $label }}</span>
            </div>
        @endforeach
    </div>
</div>
