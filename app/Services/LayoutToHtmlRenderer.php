<?php

namespace App\Services;

/**
 * Converts the builder's canvas_json into the same absolute-positioned
 * HTML/CSS format CertificateRenderService already knows how to render
 * (placeholder-substitute, then Browsershot to PDF) — so publishing a
 * builder-authored template requires zero changes to the Milestone 2
 * PDF/QR/image pipeline.
 *
 * canvas_json shape:
 * {
 *   "elements": [
 *     {
 *       "id": "el_1", "type": "text|qrcode|signature",
 *       "xPercent": 10.5, "yPercent": 20.0, "widthPercent": 40.0, "heightPercent": 8.0,
 *       "rotation": 0, "z": 1,
 *       "binding": null|"recipient_name"|"title"|"description"|"date_of_issue"|"date_of_expiry"|"organization_name"|"verification_code"|"qrcode"|"signature",
 *       "content": "Static text (only meaningful when binding is null)",
 *       "style": {"fontFamily": "Inter", "fontSize": 24, "fontWeight": "700", "color": "#151c27", "textAlign": "center"}
 *     }
 *   ]
 * }
 *
 * Percentages (not pixels) are the coordinate unit specifically so the
 * builder's editing canvas and Browsershot's render viewport never need to
 * agree on an exact pixel size — the layout scales correctly either way.
 */
class LayoutToHtmlRenderer
{
    /**
     * @param  array{elements?: array<int, array<string, mixed>>, background_html?: ?string}  $canvasJson
     */
    public function render(array $canvasJson): string
    {
        $elements = $canvasJson['elements'] ?? [];
        $backgroundHtml = $canvasJson['background_html'] ?? null;

        $body = collect($elements)
            ->sortBy('z')
            ->map(fn (array $element) => $this->renderElement($element))
            ->implode('');

        // Templates imported from hand-written HTML (see
        // TemplateBackgroundImportService) carry their original decorative
        // markup as background_html, with the fields now tracked as
        // elements blanked out of it — the overlay below is injected into
        // that existing document rather than replacing it, so the
        // decorative design survives untouched.
        if ($backgroundHtml !== null) {
            $overlay = <<<HTML
                <div style="position:absolute;top:0;left:0;right:0;bottom:0;overflow:hidden;">
                    {$body}
                </div>
                HTML;

            return $this->injectBeforeClosingBody($backgroundHtml, $overlay);
        }

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8"></head>
            <body style="margin:0;padding:0;">
                <div style="position:relative;width:100%;height:100vh;overflow:hidden;">
                    {$body}
                </div>
            </body>
            </html>
            HTML;
    }

    private function injectBeforeClosingBody(string $html, string $fragment): string
    {
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $fragment.'</body>', $html, 1) ?? ($html.$fragment);
        }

        return $html.$fragment;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function renderElement(array $element): string
    {
        $position = sprintf(
            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:%s%%;transform:rotate(%sdeg);',
            $element['xPercent'] ?? 0,
            $element['yPercent'] ?? 0,
            $element['widthPercent'] ?? 10,
            $element['heightPercent'] ?? 10,
            $element['rotation'] ?? 0,
        );

        return match ($element['type'] ?? 'text') {
            'qrcode' => $this->wrap($position, '{qrcode}'),
            'signature' => $this->wrap($position, '{signature}'),
            'company_logo' => $this->wrap($position, '{company_logo}'),
            'image' => $this->renderImage($element, $position),
            default => $this->renderText($element, $position),
        };
    }

    /**
     * Generic image slot for admin-defined custom fields (anything beyond
     * the fixed qrcode/signature/company_logo types) — binding is the
     * custom token key, e.g. {course_logo}, filled at render time from
     * Certificate::custom_image_fields via the template's custom_field_schema.
     *
     * @param  array<string, mixed>  $element
     */
    private function renderImage(array $element, string $position): string
    {
        $binding = $element['binding'] ?? null;

        if (! $binding) {
            return '';
        }

        return $this->wrap($position, '<img src="{'.$binding.'}" alt="" style="width:100%;height:100%;object-fit:contain;">');
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function renderText(array $element, string $position): string
    {
        $binding = $element['binding'] ?? null;
        $content = $binding ? '{'.$binding.'}' : e((string) ($element['content'] ?? ''));

        $style = $element['style'] ?? [];
        $textStyle = sprintf(
            'font-family:%s;font-size:%spx;font-weight:%s;color:%s;text-align:%s;',
            $style['fontFamily'] ?? 'Inter, sans-serif',
            $style['fontSize'] ?? 16,
            $style['fontWeight'] ?? '400',
            $style['color'] ?? '#151c27',
            $style['textAlign'] ?? 'left',
        );

        return $this->wrap($position.$textStyle, $content);
    }

    private function wrap(string $style, string $inner): string
    {
        return "<div style=\"{$style}\">{$inner}</div>";
    }
}
