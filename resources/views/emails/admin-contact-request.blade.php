<x-emails.layout hero-title="Contact Request" hero-subtitle="Someone reached out for support" :preheader="$contactRequest->name.' — '.$contactRequest->subject">
    <p style="margin-top:0;">Dear Admin,</p>
    <p><strong>{{ $contactRequest->name }}</strong> ({{ $contactRequest->email }})
        @if ($contactRequest->organization)
            from <strong>{{ $contactRequest->organization }}</strong>
        @endif
        submitted a contact request.</p>
    <p><strong>Subject:</strong> {{ $contactRequest->subject }}</p>
    <p style="white-space:pre-wrap;">{{ $contactRequest->message }}</p>
    <div style="text-align:center; margin:28px 0;">
        <a href="{{ route('admin.contact-requests.show', $contactRequest) }}" style="display:inline-block; background-color:#b40012; color:#ffffff; text-decoration:none; padding:12px 28px; border-radius:8px; font-weight:bold;">View Request</a>
    </div>
    <p style="margin-bottom:0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
