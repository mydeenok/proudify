<?php

namespace App\Actions\Certificates;

use App\Exceptions\SubscriptionQuotaExceededException;
use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Jobs\Certificates\GenerateCertificateQrCodeJob;
use App\Jobs\Certificates\SendCertificateIssuedEmailJob;
use App\Models\Certificate;
use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
use App\Services\CertificateRenderService;
use App\Services\SubscriptionQuotaService;
use App\Services\VerificationService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * The single certificate-creation sequence, shared by the single-issue
 * form/canvas page and every bulk-upload chunk job so the
 * create-then-generate-then-email flow exists exactly once.
 *
 * Admins issue without limit (matches the reference app); tenant users are
 * gated by subscription quota, checked and consumed atomically so
 * concurrent bulk-upload chunks can't overshoot the limit.
 *
 * Bulk upload (hundreds of rows) stays on the queue - it always has -
 * because generating that many PDFs synchronously in one web request isn't
 * viable. Single issuance (batch is null) instead generates QR/PDF/image
 * inline, so the certificate is fully ready by the time this call returns
 * and there's no "Generating your certificate..." polling page to show at
 * all. This also removes queue-worker uptime as a single-issuance failure
 * mode entirely: a certificate that gets created is a certificate that's
 * either done or has failed=logged, never stuck in limbo waiting on a
 * worker process nobody started.
 *
 * @throws SubscriptionQuotaExceededException
 */
class IssueSingleCertificateAction
{
    public function __construct(
        private readonly VerificationService $verificationService,
        private readonly SubscriptionQuotaService $quotaService,
        private readonly CertificateRenderService $renderService,
    ) {}

    /**
     * @param  array{title: string, recipient_name: string, recipient_email: string, description?: ?string, date_of_issue: string, date_of_expiry?: ?string, custom_fields?: array, custom_image_fields?: array}  $data
     */
    public function execute(User $issuer, Template $template, array $data, ?CertificateBatch $batch = null): Certificate
    {
        $userSubscription = null;

        if (! $issuer->isAdmin()) {
            $userSubscription = $this->quotaService->resolveUsableSubscription($issuer);
            $isNewRecipient = $this->quotaService->isNewRecipient($issuer, $data['recipient_email']);
            $this->quotaService->consume($userSubscription, $isNewRecipient);
        }

        $uuid = (string) Str::uuid();
        $code = $this->verificationService->generateCode();
        $signature = $this->verificationService->sign($uuid, $code, $data['date_of_issue']);

        $certificate = Certificate::create([
            'uuid' => $uuid,
            'verification_code' => $code,
            'verification_signature' => $signature,
            'user_id' => $issuer->id,
            'template_id' => $template->id,
            'certificate_batch_id' => $batch?->id,
            'user_subscription_id' => $userSubscription?->id,
            'title' => $data['title'],
            'recipient_name' => $data['recipient_name'],
            'recipient_email' => $data['recipient_email'],
            'description' => $data['description'] ?? null,
            'date_of_issue' => $data['date_of_issue'],
            'date_of_expiry' => $data['date_of_expiry'] ?? null,
            'custom_fields' => $data['custom_fields'] ?? null,
            'custom_image_fields' => $data['custom_image_fields'] ?? null,
            'company_logos' => $issuer->org_logos,
            'signature_path' => $issuer->signature_path,
        ]);

        if ($batch) {
            Bus::chain([
                new GenerateCertificateQrCodeJob($certificate),
                new GenerateCertificatePdfJob($certificate),
                new ConvertCertificatePdfToImageJob($certificate),
                new SendCertificateIssuedEmailJob($certificate),
            ])->dispatch();

            return $certificate;
        }

        // Email is deliberately excluded from the synchronous path below -
        // there's no reason the user should wait on a mail provider API
        // call to see their own certificate, and mail delivery is more
        // failure-prone / worth retrying independently of asset generation.
        $this->renderService->generateAssetsSynchronously($certificate);
        SendCertificateIssuedEmailJob::dispatch($certificate);

        return $certificate;
    }
}
