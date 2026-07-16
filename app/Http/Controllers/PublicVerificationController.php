<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicVerificationController extends Controller
{
    public function show(Request $request, string $uuid, string $code, VerificationService $verificationService): View
    {
        $result = $verificationService->verify($uuid, $code, $request);

        return view('certificates.verify', [
            'status' => $result['status'],
            'certificate' => $result['certificate'],
        ]);
    }

    /**
     * Public download, deliberately as strict as the verify page itself —
     * a revoked/expired certificate must not be downloadable just because
     * its uuid+code were once valid, so this re-runs the exact same
     * signature/status check rather than trusting the URL alone.
     */
    public function download(Request $request, string $uuid, string $code, VerificationService $verificationService)
    {
        $result = $verificationService->verify($uuid, $code, $request);

        abort_unless($result['status'] === 'valid', 404);

        $certificate = $result['certificate'];

        abort_unless($certificate->pdf_path, 404, 'The PDF is still being generated.');

        return Storage::disk('local')->download($certificate->pdf_path, "{$certificate->title}.pdf");
    }

    /**
     * Image/QR are shown on the verify page for valid, revoked, AND expired
     * certificates (only a truly not-found uuid+code pair hides them), so
     * these gate on "a real certificate was found" rather than the stricter
     * "valid" check download() uses above.
     */
    public function image(string $uuid, string $code, VerificationService $verificationService): StreamedResponse
    {
        $certificate = $this->findVerifiedCertificate($uuid, $code, $verificationService);

        abort_unless($certificate->image_path, 404);

        return Storage::disk('local')->response($certificate->image_path);
    }

    public function qr(string $uuid, string $code, VerificationService $verificationService): StreamedResponse
    {
        $certificate = $this->findVerifiedCertificate($uuid, $code, $verificationService);

        abort_unless($certificate->qr_code_path, 404);

        return Storage::disk('local')->response($certificate->qr_code_path);
    }

    private function findVerifiedCertificate(string $uuid, string $code, VerificationService $verificationService)
    {
        $result = $verificationService->resolve($uuid, $code);

        abort_if($result['status'] === 'not_found', 404);

        return $result['certificate'];
    }
}
