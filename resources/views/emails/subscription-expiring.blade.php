<x-emails.layout hero-title="Your Plan is Expiring Soon" hero-subtitle="Renew to avoid any interruption" :preheader="$userSubscription->subscription->name . ' plan expires on ' . $userSubscription->end_date->format('d M Y') . '.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>Your <strong>{{ $userSubscription->subscription->name }}</strong> plan expires on <strong>{{ $userSubscription->end_date->format('d M Y') }}</strong> ({{ $daysRemaining }} {{ Str::plural('day', $daysRemaining) }} from now).</p>
    <p style="color:#82899a;">Renew before then to avoid losing access to certificate issuance.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('subscriptions.index') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Renew Your Plan</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
