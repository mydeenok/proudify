<x-emails.layout hero-title="Subscription Cancelled" hero-subtitle="You'll retain access until it lapses" :preheader="'Your ' . $userSubscription->subscription->name . ' plan has been cancelled, effective ' . $userSubscription->end_date->format('d M Y') . '.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>Your <strong>{{ $userSubscription->subscription->name }}</strong> plan has been cancelled. You'll retain access until {{ $userSubscription->end_date->format('d M Y') }}.</p>
    <p style="color:#82899a;">Changed your mind? You can subscribe again any time.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('pricing') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Plans</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
