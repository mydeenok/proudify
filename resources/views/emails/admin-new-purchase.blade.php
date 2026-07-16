@php
    $amount = number_format((float) $userSubscription->amount_paid, 2);
@endphp
<x-emails.layout hero-title="New Purchase" hero-subtitle="A subscription was just activated" :preheader="$userSubscription->user->organization_name . ' purchased the ' . $userSubscription->subscription->name . ' plan.'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p><strong>{{ $userSubscription->user->organization_name }}</strong> ({{ $userSubscription->user->email }}) has purchased a new subscription:</p>
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f7f7f7; border-radius:8px; margin:20px 0;">
        <tr>
            <td style="padding:16px 20px; font-size:13px; color:#82899a;">Plan</td>
            <td style="padding:16px 20px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $userSubscription->subscription->name }} ({{ ucfirst($userSubscription->billing_period) }})</td>
        </tr>
        <tr>
            <td style="padding:0 20px 16px; font-size:13px; color:#82899a;">Amount Paid</td>
            <td style="padding:0 20px 16px; font-size:13px; text-align:right; font-weight:bold; color:#242b3d;">{{ $userSubscription->currency }} {{ $amount }}</td>
        </tr>
    </table>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('admin.user-subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Subscriptions</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
