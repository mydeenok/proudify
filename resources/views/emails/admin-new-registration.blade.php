<x-emails.layout hero-title="New Registration" hero-subtitle="Waiting on your approval" :preheader="$registrant->name . ' (' . $registrant->organization_name . ') is waiting for approval.'">
    <p style="margin-top:0;">Dear Admin,</p>
    <p><strong>{{ $registrant->name }}</strong> from <strong>{{ $registrant->organization_name }}</strong> ({{ $registrant->email }}) has verified their email and is now waiting for account approval.</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('admin.users.unapproved') }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">Review Pending Approvals</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
