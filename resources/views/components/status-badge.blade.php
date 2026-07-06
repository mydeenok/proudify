@props(['status' => 'default'])

@php
$class = match ($status) {
    'active', 'valid', 'verified' => 'status-pill-active',
    'pending', 'pending_approval', 'pending_otp' => 'status-pill-pending',
    'expired', 'draft' => 'status-pill-expired',
    'revoked', 'rejected', 'suspended', 'failed', 'error' => 'status-pill-error',
    default => 'inline-flex items-center px-2.5 py-0.5 rounded-full text-label-sm font-semibold bg-surface-variant text-on-surface-variant border border-outline-variant',
};
@endphp

<span {{ $attributes->merge(['class' => $class]) }}>
    {{ $slot }}
</span>
