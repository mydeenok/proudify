<x-mail::message>
# You're certified!

Hi {{ $certificate->recipient_name }},

**{{ $certificate->user->organization_name }}** has issued you a certificate: **{{ $certificate->title }}**.

Your PDF is attached. You can also view and share the publicly verifiable version:

<x-mail::button :url="$certificate->verify_url">
View & Verify Certificate
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
