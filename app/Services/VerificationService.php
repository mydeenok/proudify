<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Owns the second-factor verification code + HMAC signature that fix Bug 1
 * from the reference-app audit (verification was UUID-only, with no way to
 * detect a tampered or directly-DB-edited row). Also logs every public
 * lookup, which doubles as the data source for the verification-rate
 * analytics metric planned for Milestone 6.
 */
class VerificationService
{
    public function generateCode(): string
    {
        do {
            $code = Str::upper(Str::random(8));
        } while (Certificate::where('verification_code', $code)->exists());

        return $code;
    }

    /**
     * Signs uuid+code+date_of_issue so a certificate row edited directly in
     * the database (bypassing the app) fails verification even if the
     * uuid/code superficially match. date_of_issue is used rather than
     * created_at specifically so this can be computed once, before the
     * initial insert, instead of requiring a second write.
     */
    public function sign(string $uuid, string $code, string $dateOfIssue): string
    {
        return hash_hmac('sha256', "{$uuid}|{$code}|{$dateOfIssue}", config('app.key'));
    }

    /**
     * @return array{status: string, certificate: ?Certificate}
     */
    public function verify(string $uuid, string $code, Request $request): array
    {
        $certificate = Certificate::with(['template', 'user'])
            ->where('uuid', $uuid)
            ->where('verification_code', $code)
            ->first();

        $status = $this->resolveStatus($certificate, $uuid, $code);

        $this->log($certificate, $status, $request);

        return ['status' => $status, 'certificate' => $status === 'not_found' ? null : $certificate];
    }

    private function resolveStatus(?Certificate $certificate, string $uuid, string $code): string
    {
        if (! $certificate) {
            return 'not_found';
        }

        $expectedSignature = $this->sign($uuid, $code, $certificate->date_of_issue->toDateString());

        if (! hash_equals($expectedSignature, $certificate->verification_signature)) {
            return 'not_found';
        }

        if ($certificate->isRevoked()) {
            return 'revoked';
        }

        if ($certificate->isExpired()) {
            return 'expired';
        }

        return 'valid';
    }

    private function log(?Certificate $certificate, string $status, Request $request): void
    {
        CertificateVerification::create([
            'certificate_id' => $certificate?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'result' => $status,
        ]);
    }
}
