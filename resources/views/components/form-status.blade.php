@props(['status', 'variant' => 'success'])

@if ($status)
    @php
        $isError = $variant === 'error';
    @endphp
    <div
        x-data="{ show: true }"
        x-show="show"
        x-transition
        x-init="setTimeout(() => show = false, {{ $isError ? 5000 : 2500 }})"
        {{ $attributes->merge(['class' => 'flex items-center gap-xs font-body-sm text-body-sm rounded-lg px-md py-sm ' . ($isError ? 'text-error bg-error/10' : 'text-emerald-700 bg-emerald-500/10')]) }}
    >
        <span class="material-symbols-outlined text-[16px]">{{ $isError ? 'error' : 'check_circle' }}</span>
        <span>{{ $status }}</span>
    </div>
@endif
