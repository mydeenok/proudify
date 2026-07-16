@php
    $heroTitle = match (true) {
        $batch->failed_rows === 0 => 'Bulk Upload Complete',
        $batch->succeeded_rows === 0 => 'Bulk Upload Failed',
        default => 'Bulk Upload Completed With Errors',
    };
@endphp
<x-emails.layout :hero-title="$heroTitle" hero-subtitle="Here's how your batch turned out" :preheader="$batch->succeeded_rows . ' of ' . $batch->total_rows . ' certificates issued successfully.'">
    <p style="margin-top:0;">Dear {{ $notifiable->first_name }},</p>
    <p>Your batch of <strong>{{ $batch->total_rows }}</strong> rows has finished processing.</p>
    <p><strong>{{ $batch->succeeded_rows }}</strong> certificates were issued successfully.</p>
    @if ($batch->failed_rows > 0)
        <p style="color:#b40012;"><strong>{{ $batch->failed_rows }}</strong> rows could not be processed — see the error report for details.</p>
    @endif
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('bulk-upload.status', $batch) }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Batch</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
