<x-emails.layout hero-title="Payment Failed" hero-subtitle="We couldn't process your payment" :preheader="'Your payment for the ' . $userSubscription->subscription->name . ' plan could not be processed.'">
    <p style="margin-top:0;">Dear {{ $userSubscription->user->first_name }},</p>
    <p>We weren't able to process your payment for the <strong>{{ $userSubscription->subscription->name }}</strong> plan. Your subscription has not been activated.</p>
    <p style="color:#82899a;">This can happen for a number of reasons — insufficient funds, a bank decline, or a card issue. Please try again with the same or a different payment method.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('pricing') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Try Again</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
