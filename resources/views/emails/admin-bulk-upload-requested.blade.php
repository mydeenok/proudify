<x-emails.layout hero-title="Bulk Request Submitted" hero-subtitle="A new batch is queued for issuance" :preheader="$batch->user->organization_name . ' requested ' . $batch->total_rows . ' certificates.'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p><strong>{{ $batch->user->organization_name }}</strong> has submitted a bulk certificate request:</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f7; border-radius:8px; margin:20px 0;">
        <tr>
            <td style="padding:16px 20px; font-size:13px; color:#82899a;">Template</td>
            <td style="padding:16px 20px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $batch->template->name }}</td>
        </tr>
        <tr>
            <td style="padding:0 20px 16px; font-size:13px; color:#82899a;">Total Rows</td>
            <td style="padding:0 20px 16px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ number_format($batch->total_rows) }}</td>
        </tr>
    </table>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('bulk-upload.status', $batch) }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Batch Status</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
