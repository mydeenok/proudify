<?php

namespace App\Services;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;

/**
 * Chrome-free renderer for certificates authored purely inside the visual
 * builder. Paints each canvas_json element directly onto a raster canvas via
 * a small Node/@napi-rs/canvas script — the only PDF issuance path
 * (see CertificateRenderService::renderPdf()).
 *
 * Deliberately scoped to templates with no background_html: that field can
 * hold arbitrary hand-authored decorative HTML/CSS which this renderer has
 * no CSS engine to reproduce faithfully. Unsupported templates fail
 * CertificateRenderService::renderPdf() loudly so they get migrated rather
 * than silently needing Chromium again.
 */
class CertificateCanvasRenderService
{
    private const DESIGN_WIDTH_LANDSCAPE = 1000;

    private const DESIGN_HEIGHT_LANDSCAPE = 707;

    private const PAGE_PIXELS_AT_300DPI = [
        'a4' => ['portrait' => [2480, 3508], 'landscape' => [3508, 2480]],
        'letter' => ['portrait' => [2550, 3300], 'landscape' => [3300, 2550]],
    ];

    private const SUPPORTED_ELEMENT_TYPES = ['text', 'qrcode', 'signature', 'company_logo', 'image', 'shape'];

    /**
     * Corner padding (as % of page) matching the ~6px inset used by
     * CertificateRenderService::appendVerificationWatermark() on a ~1000px
     * design surface, and the font size (11 design-px) / colour / opacity.
     */
    private const WATERMARK_INSET_PERCENT = 0.6;

    private const WATERMARK_WIDTH_PERCENT = 22;

    private const WATERMARK_HEIGHT_PERCENT = 2.5;

    private const WATERMARK_FONT_SIZE_DESIGN = 11;

    public function supports(Certificate $certificate): bool
    {
        return $this->supportsTemplate($certificate->template);
    }

    /**
     * Same capability check as supports(), but for a Template alone — used
     * by the pre-issuance Live Preview to decide whether it can paint with
     * this same Node/Skia engine (Option A: one painter for preview + PDF)
     * or must fall back to the HTML path.
     */
    public function supportsTemplate(?Template $template): bool
    {
        if (! $template) {
            return false;
        }

        $canvasJson = $template->canvas_json;

        if (! is_array($canvasJson) || empty($canvasJson['elements'])) {
            return false;
        }

        if (($canvasJson['background_html'] ?? null) !== null) {
            return false;
        }

        foreach ($canvasJson['elements'] as $element) {
            if (! in_array($element['type'] ?? 'text', self::SUPPORTED_ELEMENT_TYPES, true)) {
                return false;
            }
        }

        return true;
    }

    public function renderPdf(Certificate $certificate): string
    {
        $template = $certificate->template;
        [$pageWidth, $pageHeight] = $this->pagePixelDimensions($template);
        $pngPath = $this->paintToPng($certificate, $pageWidth, $pageHeight);

        try {
            return $this->wrapImageAsPdf($certificate, $pngPath);
        } finally {
            @unlink($pngPath);
        }
    }

    /**
     * Pre-issuance preview using the exact same painter as renderPdf().
     * Renders at 2× design resolution (not full 300 DPI print size) so the
     * Live Preview panel stays responsive while still matching glyph
     * metrics / letter-spacing / logo placement of the issued PDF.
     *
     * @param  array{title?: ?string, recipient_name?: ?string, description?: ?string, date_of_issue?: ?string, date_of_expiry?: ?string, custom_fields?: array<string, string>}  $formData
     */
    public function renderPreviewPng(Template $template, User $issuer, array $formData, QrCodeService $qrCodeService): string
    {
        $certificate = $this->makePreviewCertificate($template, $issuer, $formData);
        $qrRelative = 'tmp/preview-qr-'.uniqid('', true).'.png';
        Storage::disk('local')->put($qrRelative, $qrCodeService->generatePngBytes(
            url('/certificates/verify/preview/SAMPLE')
        ));
        $certificate->qr_code_path = $qrRelative;

        $designWidth = $this->designWidth($template);
        $designHeight = $template->orientation === 'portrait'
            ? self::DESIGN_WIDTH_LANDSCAPE
            : self::DESIGN_HEIGHT_LANDSCAPE;
        // 2× keeps text crisp in the UI panel without the cost of a full
        // 300 DPI print raster on every keystroke.
        $scale = 2;
        $pngPath = $this->paintToPng($certificate, $designWidth * $scale, $designHeight * $scale);

        try {
            return (string) file_get_contents($pngPath);
        } finally {
            @unlink($pngPath);
            Storage::disk('local')->delete($qrRelative);
        }
    }

    /**
     * Shared Node paint step used by both the issued PDF and the live
     * preview — one code path, two output wrappers.
     */
    private function paintToPng(Certificate $certificate, int $pageWidth, int $pageHeight): string
    {
        $template = $certificate->template;
        $fontScale = $pageWidth / $this->designWidth($template);

        $elements = collect($template->canvas_json['elements'])
            ->sortBy('z')
            ->map(fn (array $element) => $this->buildElementSpec($certificate, $element, $fontScale))
            ->filter()
            ->values()
            ->all();

        $elements[] = $this->watermarkElement($template, $fontScale);

        [$backgroundColor, $backgroundImage] = $this->backgroundSpec($template);

        $specPath = tempnam(sys_get_temp_dir(), 'cert_spec_');
        $pngPath = tempnam(sys_get_temp_dir(), 'cert_png_').'.png';

        try {
            file_put_contents($specPath, json_encode([
                'width' => $pageWidth,
                'height' => $pageHeight,
                'backgroundColor' => $backgroundColor,
                'backgroundImage' => $backgroundImage,
                'elements' => $elements,
                'fonts' => $this->fontManifest(),
                'outputPath' => $pngPath,
            ], JSON_THROW_ON_ERROR));

            $this->runNodeRenderer($specPath);

            return $pngPath;
        } catch (RuntimeException $exception) {
            @unlink($pngPath);
            throw $exception;
        } finally {
            @unlink($specPath);
        }
    }

    /**
     * @param  array{title?: ?string, recipient_name?: ?string, description?: ?string, date_of_issue?: ?string, date_of_expiry?: ?string, custom_fields?: array<string, string>}  $formData
     */
    private function makePreviewCertificate(Template $template, User $issuer, array $formData): Certificate
    {
        $dateOfIssue = $formData['date_of_issue'] ?? null;
        $dateOfExpiry = $formData['date_of_expiry'] ?? null;

        $certificate = new Certificate([
            'uuid' => 'preview',
            'verification_code' => 'SAMPLE',
            'user_id' => $issuer->id,
            'template_id' => $template->id,
            'title' => $formData['title'] ?? 'Certificate Title',
            'recipient_name' => $formData['recipient_name'] ?? 'Recipient Name',
            'description' => $formData['description'] ?? '',
            'date_of_issue' => filled($dateOfIssue) ? Carbon::parse($dateOfIssue)->toDateString() : now()->toDateString(),
            'date_of_expiry' => filled($dateOfExpiry) ? Carbon::parse($dateOfExpiry)->toDateString() : null,
            'custom_fields' => $formData['custom_fields'] ?? [],
            'custom_image_fields' => [],
            'company_logos' => $issuer->org_logos,
            'signature_path' => $issuer->signature_path,
        ]);

        $certificate->setRelation('template', $template);
        $certificate->setRelation('user', $issuer);

        return $certificate;
    }

    /**
     * Pure geometry: a corner is empty when no element's bounding box
     * overlaps the corner region. Used for canvas templates so watermark
     * corner detection never needs Chrome/Browsershot.
     *
     * @return 'top-left'|'top-right'|'bottom-left'|'bottom-right'
     */
    public function detectEmptiestCornerFromCanvas(Template $template): string
    {
        $elements = $template->canvas_json['elements'] ?? [];
        $inset = self::WATERMARK_INSET_PERCENT;
        $w = self::WATERMARK_WIDTH_PERCENT;
        $h = self::WATERMARK_HEIGHT_PERCENT;

        $corners = [
            'top-left' => ['x' => $inset, 'y' => $inset, 'w' => $w, 'h' => $h],
            'top-right' => ['x' => 100 - $inset - $w, 'y' => $inset, 'w' => $w, 'h' => $h],
            'bottom-left' => ['x' => $inset, 'y' => 100 - $inset - $h, 'w' => $w, 'h' => $h],
            'bottom-right' => ['x' => 100 - $inset - $w, 'y' => 100 - $inset - $h, 'w' => $w, 'h' => $h],
        ];

        $overlap = [];

        foreach ($corners as $name => $region) {
            $area = 0.0;

            foreach ($elements as $element) {
                $area += $this->overlapArea($region, [
                    'x' => (float) ($element['xPercent'] ?? 0),
                    'y' => (float) ($element['yPercent'] ?? 0),
                    'w' => (float) ($element['widthPercent'] ?? 0),
                    'h' => (float) ($element['heightPercent'] ?? 0),
                ]);
            }

            $overlap[$name] = $area;
        }

        asort($overlap);

        return array_key_first($overlap) ?? 'bottom-left';
    }

    public function resolveWatermarkCorner(Template $template): string
    {
        if ($template->watermark_corner) {
            return $template->watermark_corner;
        }

        $corner = $this->detectEmptiestCornerFromCanvas($template);
        $template->forceFill(['watermark_corner' => $corner])->save();

        return $corner;
    }

    private function designWidth(Template $template): int
    {
        return $template->orientation === 'portrait'
            ? self::DESIGN_HEIGHT_LANDSCAPE
            : self::DESIGN_WIDTH_LANDSCAPE;
    }

    private function pagePixelDimensions(Template $template): array
    {
        $format = strtolower((string) ($template->page_format ?: 'a4'));
        $orientation = $template->orientation === 'portrait' ? 'portrait' : 'landscape';

        return self::PAGE_PIXELS_AT_300DPI[$format][$orientation]
            ?? self::PAGE_PIXELS_AT_300DPI['a4'][$orientation];
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function backgroundSpec(Template $template): array
    {
        $background = $template->canvas_json['background'] ?? null;

        if (! is_array($background)) {
            return ['#ffffff', null];
        }

        if (($background['type'] ?? null) === 'image' && is_string($background['value'] ?? null)) {
            $absolute = $this->absoluteStoragePath($background['value']);

            if ($absolute) {
                return ['#ffffff', $absolute];
            }
        }

        if (($background['type'] ?? null) === 'color' && is_string($background['value'] ?? null)) {
            return [$background['value'], null];
        }

        return ['#ffffff', null];
    }

    private function absoluteStoragePath(string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->path($path);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function buildElementSpec(Certificate $certificate, array $element, float $fontScale): ?array
    {
        $position = [
            'xPercent' => (float) ($element['xPercent'] ?? 0),
            'yPercent' => (float) ($element['yPercent'] ?? 0),
            'widthPercent' => (float) ($element['widthPercent'] ?? 10),
            'heightPercent' => (float) ($element['heightPercent'] ?? 10),
            'rotation' => (float) ($element['rotation'] ?? 0),
        ];

        return match ($element['type'] ?? 'text') {
            'qrcode' => $this->imageElement($position, $certificate->qr_code_path),
            'signature' => $this->imageElement($position, $certificate->signature_path),
            'company_logo' => $this->imageElement($position, $this->firstCompanyLogoPath((array) $certificate->company_logos)),
            'image' => $this->resolveImageElement($certificate, $element, $position),
            'shape' => $this->shapeElement($position, $element),
            default => $this->textElement($position, $certificate, $element, $fontScale),
        };
    }

    /**
     * Bound custom image fields resolve from the certificate's uploaded
     * data; unbound "decorative" images carry a fixed src on the element
     * itself (admin uploaded at design time).
     *
     * @param  array<string, mixed>  $element
     * @param  array<string, float>  $position
     */
    private function resolveImageElement(Certificate $certificate, array $element, array $position): ?array
    {
        $binding = $element['binding'] ?? null;

        if (is_string($binding) && $binding !== '') {
            // Builders historically labelled a custom-image slot "Company Logo",
            // which slugifies to binding=company_logo while keeping type=image.
            // That is a system token (issuer org logos), not a per-certificate
            // custom_image_fields upload — resolve it the same way as
            // type=company_logo so the issued PDF doesn't silently drop it.
            if ($this->isCompanyLogoBinding($binding)) {
                return $this->imageElement(
                    $position,
                    $this->companyLogoPathForBinding($certificate, $binding)
                );
            }

            return $this->imageElement($position, $this->customImagePath($certificate, $binding));
        }

        $src = $element['src'] ?? null;

        if (! is_string($src) || $src === '') {
            return null;
        }

        $absolute = $this->absoluteStoragePath($src);

        if (! $absolute) {
            return null;
        }

        return array_merge($position, [
            'kind' => 'image',
            'src' => $absolute,
        ]);
    }

    private function isCompanyLogoBinding(string $binding): bool
    {
        return $binding === 'company_logo' || (bool) preg_match('/^company_logo_\d+$/', $binding);
    }

    private function companyLogoPathForBinding(Certificate $certificate, string $binding): ?string
    {
        $logos = (array) $certificate->company_logos;

        if ($binding === 'company_logo') {
            return $this->firstCompanyLogoPath($logos);
        }

        if (preg_match('/^company_logo_(\d+)$/', $binding, $matches)) {
            $logo = $logos[(int) $matches[1]] ?? null;

            if ($logo === null) {
                return null;
            }

            return is_string($logo) ? $logo : ($logo['path'] ?? $logo['url'] ?? null);
        }

        return null;
    }

    /**
     * @param  array<string, float>  $position
     * @param  array<string, mixed>  $element
     */
    private function shapeElement(array $position, array $element): array
    {
        $style = $element['style'] ?? [];

        return array_merge($position, [
            'kind' => 'shape',
            'shapeKind' => $element['shapeKind'] ?? 'rect',
            'fill' => $style['fill'] ?? 'transparent',
            'stroke' => $style['stroke'] ?? '#151c27',
            'strokeWidth' => (float) ($style['strokeWidth'] ?? 2),
            'borderRadius' => (float) ($style['borderRadius'] ?? 0),
            'opacity' => (float) ($style['opacity'] ?? 1),
            'flipX' => (bool) ($style['flipX'] ?? false),
            'flipY' => (bool) ($style['flipY'] ?? false),
        ]);
    }

    private function imageElement(array $position, ?string $storagePath): ?array
    {
        if (! $storagePath || ! Storage::disk('local')->exists($storagePath)) {
            return null;
        }

        return array_merge($position, [
            'kind' => 'image',
            'src' => Storage::disk('local')->path($storagePath),
        ]);
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function textElement(array $position, Certificate $certificate, array $element, float $fontScale): array
    {
        $binding = $element['binding'] ?? null;
        $text = $binding ? $this->resolveTextValue($certificate, $binding) : (string) ($element['content'] ?? '');
        $style = $element['style'] ?? [];
        $fontSizeDesign = (float) ($style['fontSize'] ?? 16);
        // Fabric stores charSpacing in 1/1000 em — convert to design px
        // first, then scale to the print raster like fontSize.
        $letterSpacingDesign = CertificateTextMetrics::letterSpacingPx(
            (float) ($style['letterSpacing'] ?? 0),
            $fontSizeDesign,
        );

        return array_merge($position, [
            'kind' => 'text',
            'text' => $text,
            'fontFamily' => $style['fontFamily'] ?? 'Inter',
            'fontSize' => round($fontSizeDesign * $fontScale, 2),
            'fontWeight' => (string) ($style['fontWeight'] ?? '400'),
            'fontStyle' => (string) ($style['fontStyle'] ?? 'normal'),
            'underline' => (bool) ($style['underline'] ?? false),
            'linethrough' => (bool) ($style['linethrough'] ?? false),
            'color' => $style['color'] ?? '#151c27',
            'textAlign' => $style['textAlign'] ?? 'left',
            'opacity' => (float) ($style['opacity'] ?? 1),
            'lineHeight' => (float) ($style['lineHeight'] ?? 1.2),
            'letterSpacing' => round($letterSpacingDesign * $fontScale, 2),
            'autoFit' => (bool) ($style['autoFit'] ?? false),
            'shadow' => $this->textEffectSpec($style['shadow'] ?? null, $fontScale, [
                'color' => '#00000066', 'blur' => 4, 'offsetX' => 2, 'offsetY' => 2,
            ], ['blur', 'offsetX', 'offsetY']),
            'outline' => $this->textEffectSpec($style['outline'] ?? null, $fontScale, [
                'color' => '#151c27', 'width' => 2,
            ], ['width']),
            'highlight' => $this->textEffectSpec($style['highlight'] ?? null, $fontScale, [
                'color' => '#fff59d',
            ], []),
            'gradient' => is_array($style['gradient'] ?? null) ? [
                'from' => $style['gradient']['from'] ?? '#b40012',
                'to' => $style['gradient']['to'] ?? '#f59e0b',
            ] : null,
        ]);
    }

    /**
     * Small helper for the optional text-effect style blocks (shadow /
     * outline / highlight): null unless explicitly enabled, with numeric
     * fields scaled to the print resolution just like fontSize.
     *
     * @param  mixed  $raw
     * @param  array<string, mixed>  $defaults
     * @param  array<int, string>  $scaledKeys
     * @return array<string, mixed>|null
     */
    private function textEffectSpec(mixed $raw, float $fontScale, array $defaults, array $scaledKeys): ?array
    {
        if (! is_array($raw)) {
            return null;
        }

        $spec = array_merge($defaults, $raw);

        foreach ($scaledKeys as $key) {
            $spec[$key] = round(((float) $spec[$key]) * $fontScale, 2);
        }

        return $spec;
    }

    private function watermarkElement(Template $template, float $fontScale): array
    {
        $inset = self::WATERMARK_INSET_PERCENT;
        $w = self::WATERMARK_WIDTH_PERCENT;
        $h = self::WATERMARK_HEIGHT_PERCENT;

        [$x, $y, $align] = match ($this->resolveWatermarkCorner($template)) {
            'top-left' => [$inset, $inset, 'left'],
            'top-right' => [100 - $inset - $w, $inset, 'right'],
            'bottom-right' => [100 - $inset - $w, 100 - $inset - $h, 'right'],
            default => [$inset, 100 - $inset - $h, 'left'],
        };

        return [
            'kind' => 'text',
            'text' => 'Verified by proudify.in',
            'xPercent' => $x,
            'yPercent' => $y,
            'widthPercent' => $w,
            'heightPercent' => $h,
            'rotation' => 0,
            'fontFamily' => 'Inter',
            'fontSize' => round(self::WATERMARK_FONT_SIZE_DESIGN * $fontScale, 2),
            'fontWeight' => '400',
            'color' => '#999999',
            'textAlign' => $align,
            'opacity' => 0.75,
        ];
    }

    private function resolveTextValue(Certificate $certificate, string $binding): string
    {
        $issuer = $certificate->user;

        return match ($binding) {
            'title' => (string) $certificate->title,
            'recipient_name' => (string) $certificate->recipient_name,
            'description' => (string) $certificate->description,
            'date_of_issue' => $certificate->date_of_issue->format('d M Y'),
            'date_of_expiry' => $certificate->date_of_expiry?->format('d M Y') ?? 'No Expiry',
            'organization_name' => (string) $issuer?->organization_name,
            'verification_code' => (string) $certificate->verification_code,
            default => (string) (((array) $certificate->custom_fields)[$binding] ?? ''),
        };
    }

    private function customImagePath(Certificate $certificate, ?string $binding): ?string
    {
        if (! $binding) {
            return null;
        }

        $path = ((array) $certificate->custom_image_fields)[$binding] ?? null;

        return is_string($path) ? $path : null;
    }

    /**
     * @param  array<int, mixed>  $logos
     */
    private function firstCompanyLogoPath(array $logos): ?string
    {
        if (empty($logos)) {
            return null;
        }

        $first = $logos[0];

        return is_string($first) ? $first : ($first['path'] ?? $first['url'] ?? null);
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float}  $a
     * @param  array{x: float, y: float, w: float, h: float}  $b
     */
    private function overlapArea(array $a, array $b): float
    {
        $x1 = max($a['x'], $b['x']);
        $y1 = max($a['y'], $b['y']);
        $x2 = min($a['x'] + $a['w'], $b['x'] + $b['w']);
        $y2 = min($a['y'] + $a['h'], $b['y'] + $b['h']);

        if ($x2 <= $x1 || $y2 <= $y1) {
            return 0.0;
        }

        return ($x2 - $x1) * ($y2 - $y1);
    }

    /**
     * @return array<int, array{family: string, weight: int, src: string, cache: string}>
     */
    private function fontManifest(): array
    {
        $cacheDir = storage_path('app/fonts');

        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        return array_map(function (array $font) use ($cacheDir) {
            // Skia (@napi-rs/canvas) has no woff2 support, so each font is
            // decompressed to .ttf once and cached under a filename derived
            // from its family+weight (see certificate-canvas-render.mjs::
            // registerFonts()).
            $cacheName = str_replace(' ', '', $font['family']).'-'.($font['weight'] >= 700 ? 'Bold' : 'Regular').'.ttf';

            return [
                'family' => $font['family'],
                'weight' => $font['weight'],
                'src' => CertificateFontManifest::woff2Path($font),
                'cache' => $cacheDir.DIRECTORY_SEPARATOR.$cacheName,
            ];
        }, CertificateFontManifest::all());
    }

    private function runNodeRenderer(string $specPath): void
    {
        $timeout = (int) config('certificates.canvas_render_timeout', 30);
        $scriptPath = base_path('node/certificate-canvas-render.mjs');

        $result = Process::timeout($timeout)->run(['node', $scriptPath, $specPath]);

        if (! $result->successful()) {
            throw new RuntimeException('Canvas certificate render failed: '.$result->errorOutput());
        }
    }

    private function wrapImageAsPdf(Certificate $certificate, string $pngPath): string
    {
        $relativePath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf";
        $absolutePath = Storage::disk('local')->path($relativePath);

        Storage::disk('local')->makeDirectory(dirname($relativePath));

        $imagick = new Imagick;
        $imagick->readImage($pngPath);
        $imagick->setImageUnits(Imagick::RESOLUTION_PIXELSPERINCH);
        $imagick->setImageResolution(300, 300);
        $imagick->setImageFormat('pdf');
        $imagick->writeImage($absolutePath);
        $imagick->clear();
        $imagick->destroy();

        return $relativePath;
    }
}
