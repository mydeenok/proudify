@props(['status'])

@php
$config = match ($status) {
    'completed' => [
        'label' => 'Completed',
        'wrap' => 'bg-surface-container-high border-outline-variant text-on-surface',
        'dot' => 'bg-primary-container',
    ],
    'completed_with_errors' => [
        'label' => 'Completed with errors',
        'wrap' => 'bg-tertiary-container/30 border-outline-variant text-on-tertiary-container',
        'dot' => 'bg-tertiary',
    ],
    'processing' => [
        'label' => 'Processing',
        'wrap' => 'bg-tertiary-container/30 border-outline-variant text-on-tertiary-container',
        'dot' => 'bg-tertiary animate-pulse',
    ],
    'mapping' => [
        'label' => 'Mapping columns',
        'wrap' => 'bg-surface-variant border-outline-variant text-on-surface-variant',
        'dot' => 'bg-outline',
    ],
    'queued' => [
        'label' => 'Ready to review',
        'wrap' => 'bg-surface-variant border-outline-variant text-on-surface-variant',
        'dot' => 'bg-outline',
    ],
    'failed' => [
        'label' => 'Failed',
        'wrap' => 'bg-error-container border-outline-variant text-on-error-container',
        'dot' => 'bg-error',
    ],
    default => [
        'label' => ucfirst(str_replace('_', ' ', $status)),
        'wrap' => 'bg-surface-container-high border-outline-variant text-on-surface',
        'dot' => 'bg-outline',
    ],
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-1 rounded-full border font-label-sm text-label-sm {$config['wrap']}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
    {{ $config['label'] }}
</span>
