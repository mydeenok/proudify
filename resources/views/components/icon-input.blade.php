@props(['icon', 'disabled' => false])

<div class="relative">
    <span class="material-symbols-outlined absolute left-sm top-1/2 -translate-y-1/2 text-on-surface-variant pointer-events-none" style="font-size: 18px;">{{ $icon }}</span>
    <input @disabled($disabled) {{ $attributes->merge(['class' => 'form-input pl-10 pr-sm disabled:opacity-50 disabled:cursor-not-allowed']) }}>
</div>
