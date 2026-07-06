@props(['status'])

@php
$config = match ($status) {
    'verified', 'active' => [
        'label' => 'Verified',
        'wrap' => 'bg-surface-container-high border-outline-variant text-on-surface',
        'dot' => 'bg-primary-container',
    ],
    'pending' => [
        'label' => 'Pending',
        'wrap' => 'bg-tertiary-container/30 border-outline-variant text-on-tertiary-container',
        'dot' => 'bg-tertiary',
    ],
    'draft' => [
        'label' => 'Draft',
        'wrap' => 'bg-surface-variant border-outline-variant text-on-surface-variant',
        'dot' => 'bg-outline',
    ],
    'failed', 'revoked' => [
        'label' => $status === 'revoked' ? 'Revoked' : 'Failed',
        'wrap' => 'bg-error-container border-outline-variant text-on-error-container',
        'dot' => 'bg-error',
    ],
    'expired' => [
        'label' => 'Expired',
        'wrap' => 'bg-surface-variant border-outline-variant text-on-surface-variant',
        'dot' => 'bg-outline',
    ],
    default => [
        'label' => ucfirst($status),
        'wrap' => 'bg-surface-container-high border-outline-variant text-on-surface',
        'dot' => 'bg-primary-container',
    ],
};
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-1 px-2 py-1 rounded-full border font-label-sm text-label-sm {$config['wrap']}"]) }}>
    <span class="w-1.5 h-1.5 rounded-full {{ $config['dot'] }}"></span>
    {{ $config['label'] }}
</span>
