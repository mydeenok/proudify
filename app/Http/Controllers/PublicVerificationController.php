<?php

namespace App\Http\Controllers;

use App\Services\VerificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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

        return Storage::disk('public')->download($certificate->pdf_path, "{$certificate->title}.pdf");
    }
}
