<x-emails.layout hero-title="Congratulations!" hero-subtitle="Your account has been approved" preheader="You can now log in and start issuing certificates.">
    <p style="margin-top:0;">Dear {{ $user->first_name }},</p>
    <p>We are pleased to inform you that your Proudify account has been reviewed and approved by our team. You can now log in and start issuing certificates for your organization.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('login') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Log in to Proudify</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
