<?php

namespace App\Mail;

use App\Models\Certificate;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Support\Facades\Storage;

/**
 * Always dispatched from inside SendCertificateIssuedEmailJob, which is
 * itself queued as part of the issuance chain — so this Mailable is sent
 * synchronously within that job rather than separately implementing
 * ShouldQueue, avoiding a confusing double layer of queuing.
 */
class CertificateIssuedMail extends Mailable
{
    public function __construct(public Certificate $certificate) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been issued a certificate: {$this->certificate->title}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.certificate-issued',
            with: ['certificate' => $this->certificate],
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        if (! $this->certificate->pdf_path) {
            return [];
        }

        return [
            Attachment::fromPath(Storage::disk('public')->path($this->certificate->pdf_path))
                ->as("{$this->certificate->title}.pdf")
                ->withMime('application/pdf'),
        ];
    }
}
