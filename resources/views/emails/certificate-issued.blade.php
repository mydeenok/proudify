<x-emails.layout :hero-title="$certificate->recipient_name . ', You are Certified!'" hero-subtitle="Hearty Congratulations!" :preheader="$certificate->user->organization_name . ' has certified you for ' . $certificate->title . '.'">
    <p style="margin-top:0;">Dear {{ $certificate->recipient_name }},</p>
    <p><strong>{{ $certificate->user->organization_name }}</strong> is proud to certify you for <strong>{{ $certificate->title }}</strong>. This achievement reflects your hard work and dedication, and we're delighted to be part of your journey.</p>
    <p>Your certificate is attached to this email as a PDF. You can also view, download, and share the publicly verifiable version any time using the link below.</p>
    <p>We'd love for you to share the good news — don't forget to tag us with <strong>#ProudifyMe</strong> when you post it!</p>

    <x-slot:afterContent>
        <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation">
            <tbody>
                <tr>
                    <td class="o_px-md" align="center" style="padding-left: 24px; padding-right: 24px;">
                        <table width="100%" cellspacing="0" cellpadding="0" border="0" role="presentation" style="max-width: 584px; margin: 0 auto; background-color: #b40012; border-radius: 8px;">
                            <tbody>
                                <tr>
                                    <td class="o_sans" style="font-family: Helvetica, Arial, sans-serif; padding: 24px; color: #ffffff; vertical-align: middle; width: 60%;">
                                        <p style="margin: 0 0 4px 0; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; color: #ffd9dc;">Certified By</p>
                                        <p style="margin: 0 0 14px 0; font-size: 17px; font-weight: bold;">{{ $certificate->user->organization_name }}</p>
                                        <p style="margin: 0; font-size: 13px; color: #ffd9dc;">Issued On: <strong style="color:#ffffff;">{{ ($certificate->date_of_issue ?? $certificate->created_at)->format('d M Y') }}</strong></p>
                                        @if ($certificate->date_of_expiry)
                                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #ffd9dc;">Valid Till: <strong style="color:#ffffff;">{{ $certificate->date_of_expiry->format('d M Y') }}</strong></p>
                                        @else
                                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #ffd9dc;">Valid Till: <strong style="color:#ffffff;">Lifetime</strong></p>
                                        @endif
                                    </td>
                                    <td align="center" style="padding: 24px; vertical-align: middle; width: 40%;">
                                        <a href="{{ $certificate->verify_url }}" style="display:inline-block; background-color:#ffffff; color:#b40012; text-decoration:none; padding:12px 20px; border-radius:8px; font-weight:bold; font-size:14px;">Click to View Certificate</a>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    </x-slot>

    <p style="margin: 24px 0 0 0;">Warm Regards,<br>The {{ config('app.name') }} Team</p>
</x-emails.layout>
