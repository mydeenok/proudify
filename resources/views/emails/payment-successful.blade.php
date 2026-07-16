<x-emails.layout hero-title="Payment Successful" hero-subtitle="Your plan is now active" :preheader="'Your ' . $userSubscription->subscription->name . ' plan is now active until ' . $userSubscription->end_date->format('d M Y') . '.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>Thank you — we've received your payment and your <strong>{{ $userSubscription->subscription->name }}</strong> plan is now active.</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f7; border-radius:8px; margin:20px 0;">
        <tr>
            <td style="padding:16px 20px; font-size:13px; color:#82899a;">Plan</td>
            <td style="padding:16px 20px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $userSubscription->subscription->name }} ({{ ucfirst($userSubscription->billing_period) }})</td>
        </tr>
        <tr>
            <td style="padding:0 20px 16px; font-size:13px; color:#82899a;">Amount Paid</td>
            <td style="padding:0 20px 16px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $userSubscription->currency }} {{ number_format($userSubscription->amount_paid, 2) }}</td>
        </tr>
        <tr>
            <td style="padding:0 20px 16px; font-size:13px; color:#82899a;">Valid Until</td>
            <td style="padding:0 20px 16px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $userSubscription->end_date->format('d M Y') }}</td>
        </tr>
    </table>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('dashboard') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Go to Dashboard</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
