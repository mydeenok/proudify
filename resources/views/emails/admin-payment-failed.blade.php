<x-emails.layout hero-title="Payment Failed" hero-subtitle="A subscription purchase did not complete" :preheader="($userSubscription->user?->organization_name ?? 'A tenant').' payment failed'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p>
        Payment failed for <strong>{{ $userSubscription->user?->organization_name ?? $userSubscription->user?->name }}</strong>
        ({{ $userSubscription->user?->email }}) on plan
        <strong>{{ $userSubscription->subscription?->name ?? 'Unknown' }}</strong>.
    </p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('admin.user-subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Open User Subscriptions</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
