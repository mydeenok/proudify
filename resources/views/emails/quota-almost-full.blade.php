<x-emails.layout hero-title="You're Almost Out of Certificates" hero-subtitle="You've used {{ $percentUsed }}% of your quota" :preheader="$userSubscription->certificates_used . ' of ' . $userSubscription->certificates_limit . ' certificates used on the ' . $userSubscription->subscription->name . ' plan.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>You've used <strong>{{ $userSubscription->certificates_used }}</strong> of your <strong>{{ $userSubscription->certificates_limit }}</strong> certificates on the {{ $userSubscription->subscription->name }} plan.</p>
    <p style="color:#82899a;">Consider upgrading your plan so you don't run out mid-batch.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Plans</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
