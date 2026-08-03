@php
    $isAdmin = ($mode ?? 'tenant') === 'admin' || auth()->user()->isAdmin();
    $shell = match (true) {
        ($mode ?? 'tenant') === 'admin' && ($step ?? '') === 'setup' => 'layouts.admin-shell',
        $isAdmin && in_array($step ?? '', ['map', 'review'], true) => 'layouts.admin-shell',
        in_array($step ?? '', ['map', 'review'], true) => 'layouts.contextual-shell',
        default => 'layouts.user-shell',
    };
    $title = match ($step ?? 'template') {
        'setup' => 'Bulk Upload',
        'upload' => 'Upload Data',
        'map' => 'Map Columns',
        'review' => 'Review & Issue',
        default => 'Bulk Issue Certificates',
    };
@endphp

<x-dynamic-component :component="$shell" :title="$title">
    <livewire:bulk-upload-wizard
        :mode="$mode ?? 'tenant'"
        :step="$step ?? null"
        :template-id="$templateId ?? null"
        :batch-id="$batchId ?? null"
        :key="'wizard-'.($batchId ?? $templateId ?? $step ?? 'start').'-'.($mode ?? 'tenant')"
    />
</x-dynamic-component>
