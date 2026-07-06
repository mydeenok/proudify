<?php

namespace App\Services;

/**
 * Every template's html_content is hand-written/pasted at creation time
 * (see TemplateController) — canvas_json only exists after the FIRST
 * builder save. So opening the builder for a template with no canvas_json
 * yet always means "import this raw HTML": render it as a locked
 * background behind the Fabric canvas, and auto-detect the known
 * placeholder tokens so they become real, draggable/deletable overlay
 * elements instead of being frozen into the background forever.
 *
 * Decorative/static markup that isn't one of the known tokens can't be
 * reliably promoted to an editable object (arbitrary CSS layouts don't map
 * onto the canvas element schema) — it stays part of the locked background
 * by design.
 */
class TemplateBackgroundImportService
{
    private const TEXT_TOKENS = [
        'title' => 'Certificate Title',
        'recipient_name' => 'Recipient Name',
        'description' => 'Certificate description will appear here.',
        'date_of_issue' => 'January 1, 2026',
        'date_of_expiry' => 'No Expiry',
        'organization_name' => 'Organization Name',
        'verification_code' => 'SAMPLE1',
    ];

    private const IMAGE_TOKENS = ['qrcode', 'signature', 'company_logo'];

    public function __construct(private readonly QrCodeService $qrCodeService) {}

    /**
     * Renders html_content with every known placeholder wrapped in a
     * data-bind marker and filled with sample content, so the builder's
     * auto-detect JS can locate each field's on-page position once the
     * iframe paints.
     */
    public function renderForDetection(string $htmlContent): string
    {
        $html = $htmlContent;

        foreach (self::IMAGE_TOKENS as $token) {
            $html = $this->wrapImgTagUsingToken($html, $token);
            $html = $this->replaceImageSrcToken($html, $token, $this->sampleImageDataUri($token));
        }

        foreach (self::IMAGE_TOKENS as $token) {
            $marker = '<span data-bind="'.$token.'" style="display:inline-block;width:100px;height:100px;">'
                .'<img src="'.$this->sampleImageDataUri($token).'" alt="'.e(ucfirst($token)).'" style="width:100%;height:100%;object-fit:contain;">'
                .'</span>';
            $html = str_replace('{'.$token.'}', $marker, $html);
        }

        foreach (self::TEXT_TOKENS as $token => $sample) {
            $html = str_replace('{'.$token.'}', '<span data-bind="'.$token.'">'.e($sample).'</span>', $html);
        }

        return $html;
    }

    /**
     * Strips every known placeholder from html_content so it can be stored
     * as canvas_json's background_html — once a field is promoted to a
     * separately-tracked overlay element, leaving it in the background too
     * would render it twice.
     */
    public function blankKnownTokens(string $htmlContent): string
    {
        $html = $htmlContent;

        foreach (self::IMAGE_TOKENS as $token) {
            $pattern = '/<img\b[^>]*\bsrc=(["\'])\{'.preg_quote($token, '/').'\}\1[^>]*>/i';
            $html = preg_replace($pattern, '', $html) ?? $html;
            $html = str_replace('{'.$token.'}', '', $html);
        }

        foreach (array_keys(self::TEXT_TOKENS) as $token) {
            $html = str_replace('{'.$token.'}', '', $html);
        }

        return $html;
    }

    private function wrapImgTagUsingToken(string $html, string $token): string
    {
        $pattern = '/<img\b[^>]*\bsrc=(["\'])\{'.preg_quote($token, '/').'\}\1[^>]*>/i';

        return preg_replace_callback(
            $pattern,
            fn (array $matches) => '<span data-bind="'.$token.'" style="display:inline-block">'.$matches[0].'</span>',
            $html
        ) ?? $html;
    }

    private function replaceImageSrcToken(string $html, string $token, string $dataUri): string
    {
        $pattern = '/src=(["\'])\{'.preg_quote($token, '/').'\}\1/';

        return preg_replace($pattern, 'src=$1'.$dataUri.'$1', $html) ?? $html;
    }

    private function sampleImageDataUri(string $token): string
    {
        if ($token === 'qrcode') {
            return $this->qrCodeService->generateDataUri('https://proudify.test/sample-qr');
        }

        // Generic light-gray placeholder box for signature/logo spots —
        // there's no specific issuer context while editing a template.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="100">'
            .'<rect width="200" height="100" fill="#e5e7eb"/>'
            .'<text x="100" y="55" font-family="sans-serif" font-size="14" fill="#9ca3af" text-anchor="middle">'.e(ucfirst($token)).'</text>'
            .'</svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
