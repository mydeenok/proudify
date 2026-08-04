<x-emails.layout hero-title="Quota Nearly Full" hero-subtitle="A tenant is close to their certificate limit" :preheader="($userSubscription->user?->organization_name ?? 'A tenant').' hit '.$percentUsed.'% quota'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p>
        <strong>{{ $userSubscription->user?->organization_name ?? $userSubscription->user?->name }}</strong>
        ({{ $userSubscription->user?->email }}) has used
        <strong>{{ $percentUsed }}%</strong> of certificate quota on
        <strong>{{ $userSubscription->subscription?->name ?? 'their plan' }}</strong>
        ({{ $userSubscription->certificates_used }} / {{ $userSubscription->certificates_limit }}).
    </p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('admin.user-subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Open User Subscriptions</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
