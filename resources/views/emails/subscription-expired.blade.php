<x-emails.layout hero-title="Your Plan Has Expired" hero-subtitle="Certificate issuance is now paused" :preheader="'Renew your ' . $userSubscription->subscription->name . ' plan to resume issuing certificates.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>Your <strong>{{ $userSubscription->subscription->name }}</strong> plan expired on {{ $userSubscription->end_date->format('d M Y') }}, and certificate issuance is now paused for your account.</p>
    <p style="color:#82899a;">Renew any time to pick up right where you left off.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Renew Your Plan</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
