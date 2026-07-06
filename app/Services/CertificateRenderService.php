<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;
use Spatie\Browsershot\Browsershot;
use Throwable;

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

        $html = strtr($html, [
            '{qrcode}' => $qrcodeImg,
            '{signature}' => $signatureImg,
        ]);

        return $this->appendVerificationWatermark($html, $template);
    }

    /**
     * Renders a template with form-entered data before a Certificate row
     * exists, for the "Preview Certificate" page. There is no real UUID/
     * verification_code yet, so the QR encodes a harmless sample verify URL
     * instead of a real one; signature and logos ARE known ahead of issuance
     * (they live on the issuing user), so those render for real.
     *
     * @param  array{title?: ?string, recipient_name?: ?string, description?: ?string, date_of_issue?: ?string, date_of_expiry?: ?string, custom_fields?: array<string, string>}  $formData
     */
    public function renderPreviewHtml(Template $template, User $issuer, array $formData): string
    {
        return $this->appendVerificationWatermark(
            $this->fillPreviewTokens($template, $issuer, $formData),
            $template,
        );
    }

    /**
     * The token-substitution half of renderPreviewHtml(), split out so the
     * watermark corner-detector (which needs a sample render but must never
     * have the watermark itself baked into that sample - it would corrupt
     * its own "which corner is empty" measurement) can reuse this without
     * going through the public method's final appendVerificationWatermark()
     * call.
     *
     * @param  array{title?: ?string, recipient_name?: ?string, description?: ?string, date_of_issue?: ?string, date_of_expiry?: ?string, custom_fields?: array<string, string>}  $formData
     */
    private function fillPreviewTokens(Template $template, User $issuer, array $formData): string
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

        foreach ((array) ($formData['custom_fields'] ?? []) as $key => $value) {
            $replacements["{{$key}}"] = e((string) $value);
        }

        $replacements = array_merge($replacements, $this->companyLogoTokenReplacements((array) $issuer->org_logos));

        $html = strtr($template->html_content, $replacements);

        $html = $this->replaceImageSrcPlaceholder($html, 'qrcode', $qrcodeDataUri);
        $html = $this->replaceImageSrcPlaceholder($html, 'signature', $signatureDataUri);
        $html = $this->applyCompanyLogoSrcReplacements($html, (array) $issuer->org_logos);
        $html = $this->stripUnfilledCustomImagePlaceholders($html, $template);

        return strtr($html, [
            '{qrcode}' => $qrcodeImg,
            '{signature}' => $signatureImg,
        ]);
    }

    /**
     * The live "typing" preview has no uploaded file to render yet for any
     * custom image field (there's no Certificate row to attach an upload
     * to), so its {token} is removed entirely rather than left as a broken
     * `<img>` — a missing preview thumbnail reads better than a broken-image
     * icon in a panel that's meant to build confidence in the design.
     */
    private function stripUnfilledCustomImagePlaceholders(string $html, Template $template): string
    {
        foreach ($template->editableCustomFields() as $field) {
            if ($field['type'] !== 'image') {
                continue;
            }

            $pattern = '/<img\b[^>]*\bsrc=(["\'])\{'.preg_quote($field['key'], '/').'\}\1[^>]*>/i';
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        return $html;
    }

    /**
     * Every certificate (and its preview) carries this small verification
     * mark regardless of which template rendered it - it's appended at
     * render time rather than left up to individual templates, so there's
     * no way to end up with a certificate missing it. position:absolute
     * with no positioned ancestor places it relative to the page itself,
     * which lines up with the certificate's visible edges since every
     * template here renders at the exact page_format/orientation
     * Browsershot is told to use - the same assumption
     * LayoutToHtmlRenderer's own overlay elements already rely on.
     *
     * No font-family here deliberately - it inherits whatever font the
     * template's own body text uses, so it reads as part of each design
     * instead of always looking like the same generic Arial stamp.
     */
    private function appendVerificationWatermark(string $html, Template $template): string
    {
        $position = match ($this->resolveWatermarkCorner($template)) {
            'top-left' => 'top:6px;left:6px;',
            'top-right' => 'top:6px;right:6px;',
            'bottom-right' => 'bottom:6px;right:6px;',
            default => 'bottom:6px;left:6px;',
        };

        $watermark = '<div style="position:absolute;'.$position.'font-size:11px;line-height:1;color:#999999;opacity:0.75;z-index:9999;pointer-events:none;">Verified by proudify.in</div>';

        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $watermark.'</body>', $html, 1) ?? $html;
        }

        return $html.$watermark;
    }

    /**
     * Corner emptiness is a property of a template's own design, not of any
     * one certificate's data, so it's computed once and cached on the
     * template row - every certificate issued from it afterward just reads
     * this instantly instead of re-rendering and re-analyzing a screenshot
     * on every single issuance (which would undo the work that made
     * issuance synchronous in the first place).
     */
    private function resolveWatermarkCorner(Template $template): string
    {
        if ($template->watermark_corner) {
            return $template->watermark_corner;
        }

        $corner = $this->detectEmptiestCorner($template) ?? 'bottom-left';

        // Cached even on fallback, so a template that can't be analyzed
        // (missing creator, Chrome/Imagick hiccup) doesn't pay that cost
        // again on its next certificate.
        $template->forceFill(['watermark_corner' => $corner])->save();

        return $corner;
    }

    /**
     * Renders one sample of the template (no watermark - see
     * fillPreviewTokens) to an image and measures pixel variance in each of
     * the four corners; the corner with the least variance is the one with
     * the least visual content (background only), which is what "empty"
     * means here. Returns null on any failure so the caller can fall back
     * to a sane default rather than breaking issuance over a cosmetic
     * feature.
     */
    private function detectEmptiestCorner(Template $template): ?string
    {
        try {
            $issuer = $template->createdBy ?? User::query()->first();

            if (! $issuer) {
                return null;
            }

            $html = $this->fillPreviewTokens($template, $issuer, ['date_of_issue' => now()->toDateString()]);

            [$width, $height] = $template->orientation === 'portrait' ? [707, 1000] : [1000, 707];

            $browsershot = Browsershot::html($html)->windowSize($width, $height)->waitUntilNetworkIdle();

            if ($host = config('certificates.chrome_remote_host')) {
                $browsershot->setRemoteInstance($host, (int) config('certificates.chrome_remote_port'));
            }

            $imagick = new Imagick;
            $imagick->readImageBlob($browsershot->screenshot());

            $imageWidth = $imagick->getImageWidth();
            $imageHeight = $imagick->getImageHeight();
            $cornerWidth = max(1, (int) ($imageWidth * 0.18));
            $cornerHeight = max(1, (int) ($imageHeight * 0.12));

            $regions = [
                'top-left' => [0, 0],
                'top-right' => [$imageWidth - $cornerWidth, 0],
                'bottom-left' => [0, $imageHeight - $cornerHeight],
                'bottom-right' => [$imageWidth - $cornerWidth, $imageHeight - $cornerHeight],
            ];

            $variance = [];

            foreach ($regions as $corner => [$x, $y]) {
                $region = clone $imagick;
                $region->cropImage($cornerWidth, $cornerHeight, $x, $y);
                $stats = $region->getImageChannelStatistics();
                $variance[$corner] = $stats[Imagick::CHANNEL_RED]['standardDeviation'] ?? PHP_FLOAT_MAX;
                $region->clear();
            }

            $imagick->clear();
            asort($variance);

            return array_key_first($variance);
        } catch (Throwable $exception) {
            Log::warning('Watermark corner detection failed, defaulting to bottom-left.', [
                'template_id' => $template->id,
                'exception' => $exception->getMessage(),
            ]);

            return null;
        }
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

        // Opt-in: if CERTIFICATE_CHROME_HOST is set, connect to an
        // already-running Chrome instead of paying the ~1-3s launch cost on
        // every single PDF. Deliberately no throwOnRemoteConnectionError()
        // here - if that instance isn't reachable, Browsershot silently
        // falls back to launching its own (see bin/browser.cjs), so a dev
        // machine without a persistent Chrome running is unaffected.
        if ($host = config('certificates.chrome_remote_host')) {
            $browsershot->setRemoteInstance($host, (int) config('certificates.chrome_remote_port'));
        }

        $browsershot->savePdf($absolutePath);

        return $relativePath;
    }

    /**
     * Runs QR + PDF + image generation synchronously for one certificate -
     * shared by first-time single issuance (IssueSingleCertificateAction)
     * and the regenerate action on the certificate page, so there's one
     * implementation instead of two copies drifting apart. QR is skipped
     * if it already exists (regenerate only needs to redo PDF/image; QR
     * essentially never fails since it's pure PHP with no subprocess).
     *
     * Failure is caught and recorded rather than thrown - the certificate
     * row already exists either way, and image_generation_status =
     * 'failed' is what surfaces the existing regenerate/retry action
     * instead of turning a transient Chrome/Imagick hiccup into a hard
     * error on the whole request.
     *
     * This now runs inside the web request instead of a queue worker
     * (whose process-level timeout was previously configured separately),
     * so it explicitly raises the PHP execution time limit here - a
     * production PHP-FPM default (often 30s) could otherwise truncate a
     * slow cold-Chrome render before the local CLI dev server (unlimited
     * by default) would ever reveal the problem. Reuses job_timeout so
     * there's one config value for "how long we tolerate generation
     * taking", not two drifting apart.
     */
    public function generateAssetsSynchronously(Certificate $certificate): void
    {
        set_time_limit((int) config('certificates.job_timeout'));

        try {
            if (! $certificate->qr_code_path) {
                $certificate->forceFill(['qr_code_path' => $this->qrCodeService->generate($certificate)])->save();
            }

            $certificate->forceFill(['pdf_path' => $this->renderPdf($certificate)])->save();
            $certificate->forceFill([
                'image_path' => $this->convertPdfToImage($certificate),
                'image_generation_status' => 'completed',
            ])->save();
        } catch (Throwable $exception) {
            $certificate->forceFill(['image_generation_status' => 'failed'])->save();

            Log::error('Synchronous certificate asset generation failed.', [
                'certificate_id' => $certificate->id,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * PDF -> JPG preview conversion (Imagick-primary / CLI-fallback, same
     * as the reference app). Extracted out of ConvertCertificatePdfToImageJob
     * so both the queued job (bulk-upload path) and the synchronous
     * single-issue path (IssueSingleCertificateAction) call one
     * implementation instead of two copies drifting apart.
     */
    public function convertPdfToImage(Certificate $certificate): string
    {
        $pdfAbsolutePath = Storage::disk('public')->path($certificate->pdf_path);
        $imageRelativePath = preg_replace('/\.pdf$/', '.jpg', $certificate->pdf_path);
        $imageAbsolutePath = Storage::disk('public')->path($imageRelativePath);

        $density = (int) config('certificates.image_density');
        $quality = (int) config('certificates.image_quality');

        extension_loaded('imagick')
            ? $this->convertWithImagick($pdfAbsolutePath, $imageAbsolutePath, $density, $quality)
            : $this->convertWithCli($pdfAbsolutePath, $imageAbsolutePath, $density, $quality);

        return $imageRelativePath;
    }

    private function convertWithImagick(string $pdfPath, string $imagePath, int $density, int $quality): void
    {
        $imagick = new Imagick;
        $imagick->setResolution($density, $density);
        $imagick->readImage("{$pdfPath}[0]");
        $imagick->setImageFormat('jpg');
        $imagick->setImageCompressionQuality($quality);
        $imagick->setImageBackgroundColor('white');
        $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $imagick->writeImage($imagePath);
        $imagick->clear();
        $imagick->destroy();
    }

    private function convertWithCli(string $pdfPath, string $imagePath, int $density, int $quality): void
    {
        foreach (['magick', 'convert'] as $binary) {
            $result = Process::run([$binary, '-density', (string) $density, "{$pdfPath}[0]", '-quality', (string) $quality, $imagePath]);

            if ($result->successful()) {
                return;
            }
        }

        throw new RuntimeException('No working PDF-to-image conversion method available (Imagick and CLI fallbacks both failed).');
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
