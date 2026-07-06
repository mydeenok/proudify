<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;

/**
 * Fills a template's placeholder tokens with a certificate's real data and
 * renders the result to PDF. Page format/orientation come from the
 * template itself (see Bug 7 in the reference-app audit, where these were
 * hard-coded regardless of the template's actual design).
 */
class CertificateRenderService
{
    public function __construct(private readonly QrCodeService $qrCodeService) {}

    public function renderHtml(Certificate $certificate): string
    {
        $template = $certificate->template;
        $issuer = $certificate->user;

        $qrcodeDataUri = $this->imageDataUri($certificate->qr_code_path);
        $signatureDataUri = $this->imageDataUri($certificate->signature_path);
        $qrcodeImg = $this->imageTag($certificate->qr_code_path, 'Verification QR code', 'width:100px;height:100px;object-fit:contain;');
        $signatureImg = $this->imageTag($certificate->signature_path, 'Signature', 'max-width:180px;max-height:80px;object-fit:contain;');

        $replacements = [
            '{title}' => e($certificate->title),
            '{recipient_name}' => e($certificate->recipient_name),
            '{description}' => e($certificate->description),
            '{date_of_issue}' => $certificate->date_of_issue->format('d M Y'),
            '{date_of_expiry}' => $certificate->date_of_expiry?->format('d M Y') ?? 'No Expiry',
            '{organization_name}' => e($issuer->organization_name),
            '{verification_code}' => $certificate->verification_code,
        ];

        foreach ((array) $certificate->custom_fields as $key => $value) {
            $replacements["{{$key}}"] = e((string) $value);
        }

        $replacements = array_merge($replacements, $this->companyLogoTokenReplacements((array) $certificate->company_logos));

        $html = strtr($template->html_content, $replacements);

        $html = $this->replaceImageSrcPlaceholder($html, 'qrcode', $qrcodeDataUri);
        $html = $this->replaceImageSrcPlaceholder($html, 'signature', $signatureDataUri);
        $html = $this->applyCompanyLogoSrcReplacements($html, (array) $certificate->company_logos);
        $html = $this->applyCustomImageFieldReplacements($html, $template, (array) $certificate->custom_image_fields);

        return strtr($html, [
            '{qrcode}' => $qrcodeImg,
            '{signature}' => $signatureImg,
        ]);
    }

    /**
     * Renders a template with form-entered data before a Certificate row
     * exists, for the "Preview Certificate" page. There is no real UUID/
     * verification_code yet, so the QR encodes a harmless sample verify URL
     * instead of a real one; signature and logos ARE known ahead of issuance
     * (they live on the issuing user), so those render for real.
     *
     * @param  array{title?: ?string, recipient_name?: ?string, description?: ?string, date_of_issue?: ?string, date_of_expiry?: ?string}  $formData
     */
    public function renderPreviewHtml(Template $template, User $issuer, array $formData): string
    {
        $dateOfIssue = $formData['date_of_issue'] ?? null;

        $qrcodeDataUri = $this->qrCodeService->generateDataUri(url('/certificates/verify/preview/SAMPLE'));
        $signatureDataUri = $this->imageDataUri($issuer->signature_path);
        $qrcodeImg = '<img src="'.$qrcodeDataUri.'" alt="Sample verification QR code" style="width:100px;height:100px;object-fit:contain;">';
        $signatureImg = $this->imageTag($issuer->signature_path, 'Signature', 'max-width:180px;max-height:80px;object-fit:contain;');

        $replacements = [
            '{title}' => e($formData['title'] ?? 'Certificate Title'),
            '{recipient_name}' => e($formData['recipient_name'] ?? 'Recipient Name'),
            '{description}' => e($formData['description'] ?? ''),
            '{date_of_issue}' => $dateOfIssue ? Carbon::parse($dateOfIssue)->format('d M Y') : now()->format('d M Y'),
            '{date_of_expiry}' => ($formData['date_of_expiry'] ?? null) ? Carbon::parse($formData['date_of_expiry'])->format('d M Y') : 'No Expiry',
            '{organization_name}' => e($issuer->organization_name),
            '{verification_code}' => 'SAMPLE',
        ];

        $replacements = array_merge($replacements, $this->companyLogoTokenReplacements((array) $issuer->org_logos));

        $html = strtr($template->html_content, $replacements);

        $html = $this->replaceImageSrcPlaceholder($html, 'qrcode', $qrcodeDataUri);
        $html = $this->replaceImageSrcPlaceholder($html, 'signature', $signatureDataUri);
        $html = $this->applyCompanyLogoSrcReplacements($html, (array) $issuer->org_logos);

        return strtr($html, [
            '{qrcode}' => $qrcodeImg,
            '{signature}' => $signatureImg,
        ]);
    }

    public function renderPdf(Certificate $certificate): string
    {
        $html = $this->renderHtml($certificate);

        $relativePath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf";
        $absolutePath = Storage::disk('public')->path($relativePath);

        Storage::disk('public')->makeDirectory(dirname($relativePath));

        $browsershot = Browsershot::html($html)
            ->format($certificate->template->page_format)
            ->margins(0, 0, 0, 0)
            ->showBackground()
            ->waitUntilNetworkIdle();

        $certificate->template->orientation === 'landscape'
            ? $browsershot->landscape()
            : $browsershot->portrait();

        $browsershot->savePdf($absolutePath);

        return $relativePath;
    }

    /**
     * Browsershot rejects `file://` URIs in HTML outright (a deliberate
     * SSRF/local-file-read guard) so images are inlined as base64 data
     * URIs instead — safer anyway, since it works even if template HTML
     * is ever user-editable without opening a path-traversal surface.
     */
    private function imageDataUri(?string $path): string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return '';
        }

        $mimeType = Storage::disk('public')->mimeType($path);
        $base64 = base64_encode(Storage::disk('public')->get($path));

        return 'data:'.$mimeType.';base64,'.$base64;
    }

    private function imageTag(?string $path, string $alt, string $style = 'max-width: 120px; max-height: 120px; object-fit: contain;'): string
    {
        $dataUri = $this->imageDataUri($path);

        if ($dataUri === '') {
            return '';
        }

        return '<img src="'.$dataUri.'" alt="'.e($alt).'" style="'.$style.'">';
    }

    private function replaceImageSrcPlaceholder(string $html, string $token, string $dataUri): string
    {
        if ($dataUri === '') {
            return $html;
        }

        $pattern = '/src=(["\'])\{'.preg_quote($token, '/').'\}\1/';

        return preg_replace($pattern, 'src=$1'.$dataUri.'$1', $html) ?? $html;
    }

    /**
     * @param  array<int, mixed>  $logos
     * @return array<string, string>
     */
    private function companyLogoTokenReplacements(array $logos): array
    {
        $replacements = [];

        foreach ($logos as $index => $logo) {
            $path = is_string($logo) ? $logo : ($logo['path'] ?? $logo['url'] ?? null);
            $replacements["{company_logo_{$index}}"] = $this->imageDataUri($path);
        }

        if (! empty($logos)) {
            $firstLogo = $logos[0];
            $firstPath = is_string($firstLogo) ? $firstLogo : ($firstLogo['path'] ?? $firstLogo['url'] ?? null);
            $replacements['{company_logo}'] = $this->imageDataUri($firstPath);
        }

        return $replacements;
    }

    /**
     * @param  array<int, mixed>  $logos
     */
    private function applyCompanyLogoSrcReplacements(string $html, array $logos): string
    {
        foreach ($logos as $index => $logo) {
            $path = is_string($logo) ? $logo : ($logo['path'] ?? $logo['url'] ?? null);
            $html = $this->replaceImageSrcPlaceholder($html, "company_logo_{$index}", $this->imageDataUri($path));
        }

        if (! empty($logos)) {
            $firstLogo = $logos[0];
            $firstPath = is_string($firstLogo) ? $firstLogo : ($firstLogo['path'] ?? $firstLogo['url'] ?? null);
            $html = $this->replaceImageSrcPlaceholder($html, 'company_logo', $this->imageDataUri($firstPath));
        }

        return $html;
    }

    /**
     * Only replaces tokens the template's own custom_field_schema declares
     * as type=image — the schema is the sole authority on which {tokens}
     * a certificate's custom_image_fields may fill, so a stray key in the
     * data that isn't in the schema is silently ignored rather than opening
     * an arbitrary-token injection surface into the template HTML.
     *
     * @param  array<string, mixed>  $customImageFields
     */
    private function applyCustomImageFieldReplacements(string $html, Template $template, array $customImageFields): string
    {
        foreach ($template->editableCustomFields() as $field) {
            if ($field['type'] !== 'image') {
                continue;
            }

            $path = $customImageFields[$field['key']] ?? null;
            $html = $this->replaceImageSrcPlaceholder($html, $field['key'], $this->imageDataUri(is_string($path) ? $path : null));
        }

        return $html;
    }
}
