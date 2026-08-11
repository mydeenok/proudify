<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CertificateResource;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = min($request->integer('per_page', 15), 100);

        $certificates = $request->user()
            ->certificates()
            ->latest()
            ->paginate($perPage);

        return CertificateResource::collection($certificates);
    }

    /**
     * Deliberately not implicit route-model binding: a naive
     * Certificate::where('uuid', ...)->firstOrFail() followed by a
     * separate ownership check would let a caller distinguish "doesn't
     * exist" from "exists but isn't yours" via the response shape before
     * the ownership check runs. Scoping the query itself through the
     * authenticated user's own certificates() relation collapses both
     * cases into the same 404, the same way the tenant scoping works
     * throughout the rest of this app.
     */
    public function show(Request $request, string $uuid): CertificateResource
    {
        return new CertificateResource($this->ownCertificate($request, $uuid));
    }

    /**
     * Bearer-token equivalent of CertificateController::image() - that
     * route is session-cookie-gated and not reachable with an API token,
     * so tenants pulling certificate data via the API had metadata but no
     * way to fetch the actual asset.
     */
    public function image(Request $request, string $uuid): StreamedResponse
    {
        $certificate = $this->ownCertificate($request, $uuid);

        abort_unless($certificate->image_path, 404);

        return Storage::disk('local')->response($certificate->image_path);
    }

    public function download(Request $request, string $uuid): StreamedResponse
    {
        $certificate = $this->ownCertificate($request, $uuid);

        abort_unless($certificate->pdf_path, 404, 'The PDF is still being generated.');

        return Storage::disk('local')->download($certificate->pdf_path, "{$certificate->title}.pdf");
    }

    public function qr(Request $request, string $uuid): StreamedResponse
    {
        $certificate = $this->ownCertificate($request, $uuid);

        abort_unless($certificate->qr_code_path, 404);

        return Storage::disk('local')->response($certificate->qr_code_path);
    }

    /**
     * Accepts either the internal uuid or the public verification_code
     * (the "Credential ID" shown to recipients) in the same path segment -
     * an external integration is more likely to have the code printed on
     * the certificate than the uuid, which is never shown to anyone.
     *
     * The two columns are compared inside a single nested where() rather
     * than a bare orWhere() - an unwrapped orWhere() after certificates()'s
     * own user_id constraint would OR against the whole query, not just
     * this condition, and match another tenant's certificate by code.
     */
    private function ownCertificate(Request $request, string $uuid): Certificate
    {
        return $request->user()
            ->certificates()
            ->where(function ($query) use ($uuid) {
                $query->where('uuid', $uuid)->orWhere('verification_code', $uuid);
            })
            ->firstOrFail();
    }
}
