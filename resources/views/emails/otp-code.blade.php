<x-emails.layout hero-title="Verify Your Email" hero-subtitle="One Time Password" :preheader="'Your OTP is ' . $otpCode . ' — valid for 10 minutes.'">
    <p style="margin-top:0;">Dear {{ $user->first_name }},</p>
    <p>Thank you for registering with Proudify. Please use the One Time Password (OTP) below to verify your email address. This OTP is valid for <strong>10 minutes</strong> only.</p>
    <div style="text-align:center; margin:28px 0;">
        <span style="display:inline-block; font-size:32px; font-weight:bold; letter-spacing:8px; color:#242b3d; background-color:#f7f7f7; padding:16px 24px; border-radius:8px;">{{ $otpCode }}</span>
    </div>
    <p style="color:#82899a;">If you did not request this code, you can safely ignore this email.</p>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
