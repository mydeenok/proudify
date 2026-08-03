@php
    $heroTitle = match (true) {
        $batch->failed_rows === 0 => 'Bulk Upload Completed',
        $batch->succeeded_rows === 0 => 'Bulk Upload Failed',
        default => 'Bulk Upload Needs Attention',
    };
@endphp

<x-emails.layout :hero-title="$heroTitle" hero-subtitle="A tenant batch finished processing" :preheader="$batch->succeeded_rows.' of '.$batch->total_rows.' certificates issued'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p>
        Bulk upload for <strong>{{ $batch->user?->organization_name ?? 'Unknown organization' }}</strong>
        ({{ $batch->user?->email }}) finished with status <strong>{{ str_replace('_', ' ', $batch->status) }}</strong>.
    </p>
    <ul>
        <li>Succeeded: {{ $batch->succeeded_rows }}</li>
        <li>Failed: {{ $batch->failed_rows }}</li>
        <li>Total rows: {{ $batch->total_rows }}</li>
    </ul>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('bulk-upload.status', $batch) }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Batch Status</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
