<x-emails.layout hero-title="Registration Update" hero-subtitle="We've reviewed your registration request" preheader="Your registration request could not be approved at this time.">
    <p style="margin-top:0;">Dear {{ $user->first_name }},</p>
    <p>Thank you for your interest in Proudify. After careful review, your registration request for <strong>{{ $user->email }}</strong> has been reviewed and could not be approved at this time.</p>
    <p style="color:#82899a;">If you believe this is a mistake or would like more information, please reach out to our support team.</p>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
