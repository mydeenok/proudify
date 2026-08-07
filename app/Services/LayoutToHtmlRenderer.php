<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

/**
 * Converts the builder's canvas_json into the same absolute-positioned
 * HTML/CSS format CertificateRenderService already knows how to render
 * (placeholder-substitute, then Browsershot to PDF) — so publishing a
 * builder-authored template requires zero changes to the Milestone 2
 * PDF/QR/image pipeline. This is also what powers the admin "Live Preview"
 * pane and the /certificates/preview page, so it needs to stay in visual
 * parity with CertificateCanvasRenderService + certificate-canvas-render.mjs
 * (the Chrome-free path) even though it renders through Browsershot/Chrome.
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
        $pageBackground = $this->pageBackgroundStyle($canvasJson['background'] ?? null);

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

        $fontFaces = $this->embedFontFaces($elements);

        return <<<HTML
            <!DOCTYPE html>
            <html>
            <head><meta charset="utf-8">{$fontFaces}</head>
            <body style="margin:0;padding:0;">
                <div style="position:relative;width:100%;height:100vh;overflow:hidden;{$pageBackground}">
                    {$body}
                </div>
            </body>
            </html>
            HTML;
    }

    /**
     * Without this, every custom font choice in the builder (Montserrat,
     * Playfair Display, Pacifico, ...) silently falls back to the browser's
     * generic sans-serif inside this document — a `srcdoc` iframe (Live
     * Preview / /certificates/preview) is a fully isolated document with no
     * access to whatever @fontsource CSS the builder's own page loaded, and
     * this renderer never linked/embedded any font files of its own. Since
     * different fonts have very different cap-height/x-height ratios at the
     * same nominal font-size, this alone was a large part of why headings
     * looked visually "smaller"/"off" compared to the Fabric builder canvas
     * even though the font-size value itself was always correct. Embedding
     * as base64 data URIs (rather than a relative <link>) keeps this
     * renderer's HTML fully self-contained, which Browsershot already
     * depends on for the images/backgrounds handled above.
     *
     * @param  array<int, array<string, mixed>>  $elements
     */
    private function embedFontFaces(array $elements): string
    {
        $families = collect($elements)
            ->filter(fn (array $element) => ($element['type'] ?? 'text') === 'text')
            ->map(fn (array $element) => (string) ($element['style']['fontFamily'] ?? 'Inter'))
            // Always-present fallback chain - see fontFamilyStack() below.
            ->push('Inter')
            ->push('Noto Sans')
            ->push('Noto Sans Tamil')
            ->unique();

        if ($families->isEmpty()) {
            return '';
        }

        $rules = '';

        foreach (CertificateFontManifest::all() as $font) {
            if (! $families->contains($font['family'])) {
                continue;
            }

            $path = CertificateFontManifest::woff2Path($font);

            if (! is_file($path)) {
                continue;
            }

            $base64 = base64_encode(file_get_contents($path));
            $family = addslashes($font['family']);
            $rules .= "@font-face{font-family:'{$family}';font-weight:{$font['weight']};font-style:normal;font-display:block;src:url(data:font/woff2;base64,{$base64}) format('woff2');}\n";
        }

        return $rules === '' ? '' : '<style>'.$rules.'</style>';
    }

    /**
     * @param  array{type?: string, value?: string}|null  $background
     */
    private function pageBackgroundStyle(?array $background): string
    {
        if (! is_array($background)) {
            return 'background-color:#ffffff;';
        }

        if (($background['type'] ?? null) === 'color' && is_string($background['value'] ?? null)) {
            return 'background-color:'.e($background['value']).';';
        }

        if (($background['type'] ?? null) === 'image' && is_string($background['value'] ?? null)) {
            $dataUri = $this->imageDataUri($background['value']);

            if ($dataUri !== null) {
                return 'background-color:#ffffff;background-image:url('.$dataUri.');background-size:cover;background-position:center;background-repeat:no-repeat;';
            }
        }

        return 'background-color:#ffffff;';
    }

    /**
     * Background/decorative images are uploaded via the builder's asset
     * endpoint onto the 'public' disk, but templates imported from
     * hand-written HTML can also reference 'local' paths — check both
     * (mirrors CertificateCanvasRenderService::absoluteStoragePath()) and
     * inline as a data URI so Browsershot never needs a live HTTP round
     * trip to render it.
     */
    private function imageDataUri(string $path): ?string
    {
        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                $mimeType = Storage::disk($disk)->mimeType($path) ?: 'image/png';
                $base64 = base64_encode(Storage::disk($disk)->get($path));

                return 'data:'.$mimeType.';base64,'.$base64;
            }
        }

        return null;
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
            'position:absolute;left:%s%%;top:%s%%;width:%s%%;height:%s%%;',
            $element['xPercent'] ?? 0,
            $element['yPercent'] ?? 0,
            $element['widthPercent'] ?? 10,
            $element['heightPercent'] ?? 10,
        );
        $rotation = (float) ($element['rotation'] ?? 0);

        return match ($element['type'] ?? 'text') {
            'qrcode' => $this->wrap($position.$this->transformStyle($rotation), '{qrcode}'),
            'signature' => $this->wrap($position.$this->transformStyle($rotation), '{signature}'),
            'company_logo' => $this->wrap($position.$this->transformStyle($rotation), '{company_logo}'),
            'image' => $this->renderImage($element, $position, $rotation),
            'shape' => $this->renderShape($element, $position, $rotation),
            default => $this->renderText($element, $position, $rotation),
        };
    }

    /**
     * Combines rotation with an optional shape flip into a single CSS
     * `transform` value — the two can never be emitted as separate
     * `transform:` declarations on the same element, the later one would
     * simply overwrite the former instead of composing with it.
     */
    private function transformStyle(float $rotation, bool $flipX = false, bool $flipY = false): string
    {
        $parts = ["rotate({$rotation}deg)"];

        if ($flipX) {
            $parts[] = 'scaleX(-1)';
        }

        if ($flipY) {
            $parts[] = 'scaleY(-1)';
        }

        return 'transform:'.implode(' ', $parts).';';
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function renderShape(array $element, string $position, float $rotation): string
    {
        $style = $element['style'] ?? [];
        $kind = $element['shapeKind'] ?? 'rect';
        $fill = $style['fill'] ?? 'transparent';
        $stroke = $style['stroke'] ?? '#151c27';
        $strokeWidth = (float) ($style['strokeWidth'] ?? 2);
        $radius = (float) ($style['borderRadius'] ?? 0);
        $opacity = (float) ($style['opacity'] ?? 1);
        $flipX = (bool) ($style['flipX'] ?? false);
        $flipY = (bool) ($style['flipY'] ?? false);
        $transform = $this->transformStyle($rotation, $flipX, $flipY);
        $opacityStyle = "opacity:{$opacity};";

        // 'line' is not a special case: the builder represents it as a
        // plain Fabric.Rect with a small heightPercent (a thin filled bar),
        // not an actual stroked line - see certificate-builder.js's
        // addShape('line'), which sets fill to the bar color and
        // strokeWidth to 0. Falling through to the generic filled-box
        // branch below (instead of drawing a border-top sized by
        // strokeWidth) is what makes it visible, matching the builder.
        if ($kind === 'circle') {
            return $this->wrap(
                $position.$transform.$opacityStyle.'background:'.e($fill).';border:'.$strokeWidth.'px solid '.e($stroke).';box-sizing:border-box;border-radius:50%;',
                ''
            );
        }

        // Polygon-based shapes (triangle, diamond, pentagon, hexagon, star,
        // seal, arrow, banner, heart) mirror the exact vertex math in
        // certificate-canvas-render.mjs::shapeVertices()/traceHeart(), just
        // expressed as CSS clip-path percentages instead of canvas paths —
        // percentages resolve against this element's own box, so a
        // clip-path here fills the same footprint the Node/Fabric
        // renderers draw into.
        $vertices = $this->shapeVertices($kind);

        if ($vertices !== null) {
            $clipPath = 'polygon('.implode(', ', array_map(
                fn (array $point) => round($point[0], 2).'% '.round($point[1], 2).'%',
                $vertices
            )).')';

            return $this->wrap(
                $position.$transform.$opacityStyle.'background:'.e($fill).';border:'.$strokeWidth.'px solid '.e($stroke).';box-sizing:border-box;clip-path:'.$clipPath.';',
                ''
            );
        }

        $extra = $radius > 0 ? 'border-radius:'.$radius.'px;' : '';

        return $this->wrap(
            $position.$transform.$opacityStyle.'background:'.e($fill).';border:'.$strokeWidth.'px solid '.e($stroke).';box-sizing:border-box;'.$extra,
            ''
        );
    }

    /**
     * Vertex lists (as percent-of-box [x, y] pairs) for every polygon-based
     * shape kind. Kept numerically in sync with the shared (w, h) box math
     * in certificate-canvas-render.mjs::shapeVertices() / heartPathData() in
     * certificate-builder.js — only the output format differs (CSS
     * clip-path percentages here vs. canvas path commands there).
     *
     * @return array<int, array{0: float, 1: float}>|null
     */
    private function shapeVertices(string $kind): ?array
    {
        $w = 100.0;
        $h = 100.0;

        return match ($kind) {
            'triangle' => [[$w / 2, 0], [$w, $h], [0, $h]],
            'diamond' => [[$w / 2, 0], [$w, $h / 2], [$w / 2, $h], [0, $h / 2]],
            'pentagon' => $this->normalizeToBox($this->regularPolygonPoints(5, $w, $h), $w, $h),
            'hexagon' => $this->normalizeToBox($this->regularPolygonPoints(6, $w, $h), $w, $h),
            'star' => $this->normalizeToBox($this->starPolygonPoints(5, 0.5, $w, $h), $w, $h),
            'seal' => $this->normalizeToBox($this->starPolygonPoints(24, 0.88, $w, $h), $w, $h),
            'arrow' => [
                [0, 0.3 * $h], [0.6 * $w, 0.3 * $h], [0.6 * $w, 0],
                [$w, $h / 2], [0.6 * $w, $h], [0.6 * $w, 0.7 * $h], [0, 0.7 * $h],
            ],
            'banner' => [
                [0.08 * $w, 0], [0.92 * $w, 0], [$w, $h / 2],
                [0.92 * $w, $h], [0.08 * $w, $h], [0, $h / 2],
            ],
            'heart' => $this->heartPolygonPoints(),
            default => null,
        };
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function regularPolygonPoints(int $sides, float $w, float $h): array
    {
        $cx = $w / 2;
        $cy = $h / 2;
        $rx = $w / 2;
        $ry = $h / 2;
        $start = -M_PI / 2;
        $points = [];

        for ($i = 0; $i < $sides; $i++) {
            $angle = $start + ($i * 2 * M_PI) / $sides;
            $points[] = [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
        }

        return $points;
    }

    /**
     * @return array<int, array{0: float, 1: float}>
     */
    private function starPolygonPoints(int $spikes, float $innerRatio, float $w, float $h): array
    {
        $cx = $w / 2;
        $cy = $h / 2;
        $outerRx = $w / 2;
        $outerRy = $h / 2;
        $innerRx = $outerRx * $innerRatio;
        $innerRy = $outerRy * $innerRatio;
        $start = -M_PI / 2;
        $step = M_PI / $spikes;
        $points = [];

        for ($i = 0; $i < $spikes * 2; $i++) {
            $angle = $start + $i * $step;
            $rx = $i % 2 === 0 ? $outerRx : $innerRx;
            $ry = $i % 2 === 0 ? $outerRy : $innerRy;
            $points[] = [$cx + $rx * cos($angle), $cy + $ry * sin($angle)];
        }

        return $points;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     * @return array<int, array{0: float, 1: float}>
     */
    private function normalizeToBox(array $points, float $w, float $h): array
    {
        $minX = INF;
        $minY = INF;
        $maxX = -INF;
        $maxY = -INF;

        foreach ($points as [$x, $y]) {
            $minX = min($minX, $x);
            $maxX = max($maxX, $x);
            $minY = min($minY, $y);
            $maxY = max($maxY, $y);
        }

        $boundsWidth = ($maxX - $minX) ?: 1;
        $boundsHeight = ($maxY - $minY) ?: 1;

        return array_map(
            fn (array $point) => [(($point[0] - $minX) / $boundsWidth) * $w, (($point[1] - $minY) / $boundsHeight) * $h],
            $points
        );
    }

    /**
     * Approximates the heart's 4 cubic-bezier curves (see traceHeart() in
     * certificate-canvas-render.mjs) as a many-point polygon, since CSS
     * clip-path's path() syntax only accepts absolute px coordinates while
     * polygon() accepts percentages — sampling the curves keeps this
     * percentage-based (so it still scales with the element's own box).
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function heartPolygonPoints(): array
    {
        $curves = [
            [[0.5, 0.85], [0.15, 0.6], [-0.05, 0.35], [0.15, 0.15]],
            [[0.15, 0.15], [0.3, 0.0], [0.5, 0.1], [0.5, 0.3]],
            [[0.5, 0.3], [0.5, 0.1], [0.7, 0.0], [0.85, 0.15]],
            [[0.85, 0.15], [1.05, 0.35], [0.85, 0.6], [0.5, 0.85]],
        ];

        $minX = -0.05;
        $maxX = 1.05;
        $minY = 0.0;
        $maxY = 0.85;
        $steps = 14;
        $points = [];

        foreach ($curves as $curve) {
            [$p0, $p1, $p2, $p3] = $curve;

            for ($i = 0; $i <= $steps; $i++) {
                if ($i === 0 && ! empty($points)) {
                    continue;
                }

                $t = $i / $steps;
                $u = $this->cubicBezier($p0[0], $p1[0], $p2[0], $p3[0], $t);
                $v = $this->cubicBezier($p0[1], $p1[1], $p2[1], $p3[1], $t);
                $points[] = [
                    (($u - $minX) / ($maxX - $minX)) * 100,
                    (($v - $minY) / ($maxY - $minY)) * 100,
                ];
            }
        }

        return $points;
    }

    private function cubicBezier(float $p0, float $p1, float $p2, float $p3, float $t): float
    {
        $mt = 1 - $t;

        return $mt ** 3 * $p0 + 3 * $mt ** 2 * $t * $p1 + 3 * $mt * $t ** 2 * $p2 + $t ** 3 * $p3;
    }

    /**
     * Generic image slot for admin-defined custom fields (anything beyond
     * the fixed qrcode/signature/company_logo types) — binding is the
     * custom token key, e.g. {course_logo}, filled at render time from
     * Certificate::custom_image_fields via the template's custom_field_schema.
     *
     * @param  array<string, mixed>  $element
     */
    private function renderImage(array $element, string $position, float $rotation): string
    {
        $binding = $element['binding'] ?? null;
        $transform = $this->transformStyle($rotation);

        if (is_string($binding) && $binding !== '') {
            return $this->wrap($position.$transform, '<img src="{'.$binding.'}" alt="" style="width:100%;height:100%;object-fit:contain;">');
        }

        // Decorative (admin-uploaded, no binding) — the canvas driver
        // paints these from element.src directly; inline the same file
        // as a data URI here so the Browsershot/HTML path (live preview
        // and any browsershot-driven issuance) shows it too.
        $src = $element['src'] ?? null;

        if (is_string($src) && $src !== '') {
            $dataUri = $this->imageDataUri($src);

            if ($dataUri !== null) {
                return $this->wrap($position.$transform, '<img src="'.$dataUri.'" alt="" style="width:100%;height:100%;object-fit:contain;">');
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function renderText(array $element, string $position, float $rotation): string
    {
        $binding = $element['binding'] ?? null;
        $content = $binding ? '{'.$binding.'}' : e((string) ($element['content'] ?? ''));
        $style = $element['style'] ?? [];

        $decorations = array_filter([
            ! empty($style['underline']) ? 'underline' : null,
            ! empty($style['linethrough']) ? 'line-through' : null,
        ]);

        $fontSize = (float) ($style['fontSize'] ?? 16);
        // Fabric charSpacing is 1/1000 em — see CertificateTextMetrics.
        $letterSpacingPx = CertificateTextMetrics::letterSpacingPx(
            (float) ($style['letterSpacing'] ?? 0),
            $fontSize,
        );

        $boxStyle = sprintf(
            'font-family:%s;font-size:%spx;font-weight:%s;font-style:%s;text-align:%s;text-decoration:%s;opacity:%s;line-height:%s;letter-spacing:%spx;white-space:pre-wrap;word-break:break-word;',
            $this->fontFamilyStack($style['fontFamily'] ?? 'Inter'),
            $fontSize,
            $style['fontWeight'] ?? '400',
            ($style['fontStyle'] ?? 'normal') === 'italic' ? 'italic' : 'normal',
            $style['textAlign'] ?? 'left',
            $decorations ? implode(' ', $decorations) : 'none',
            (float) ($style['opacity'] ?? 1),
            (float) ($style['lineHeight'] ?? 1.2),
            $letterSpacingPx,
        );

        return $this->wrap(
            $position.$boxStyle.$this->transformStyle($rotation),
            '<span style="'.$this->textSpanStyle($style).'">'.$content.'</span>'
        );
    }

    /**
     * Mirrors certificate-canvas-render.mjs::fontStack(): always trail with
     * Noto Sans Tamil (then Noto Sans, then Inter) so mixed Tamil+Latin
     * text falls back to a glyph that actually exists instead of a tofu
     * box, whichever family the element itself asked for.
     */
    private function fontFamilyStack(string $family): string
    {
        if ($family === 'Noto Sans Tamil') {
            return '"Noto Sans Tamil","Inter"';
        }

        return '"'.addslashes($family).'","Noto Sans Tamil","Noto Sans","Inter"';
    }

    /**
     * Fill (solid or gradient) plus the optional shadow/outline/highlight
     * effects, all scoped to an inline <span> around just the text content
     * rather than the full positioned box — a highlight in particular
     * should hug the glyphs, not fill the whole placeholder rectangle.
     *
     * @param  array<string, mixed>  $style
     */
    private function textSpanStyle(array $style): string
    {
        $css = '';

        if (is_array($style['gradient'] ?? null)) {
            $from = $style['gradient']['from'] ?? '#b40012';
            $to = $style['gradient']['to'] ?? '#f59e0b';
            $css .= 'background-image:linear-gradient(90deg,'.e($from).','.e($to).');-webkit-background-clip:text;background-clip:text;color:transparent;';
        } else {
            $css .= 'color:'.e($style['color'] ?? '#151c27').';';
        }

        if (is_array($style['shadow'] ?? null)) {
            $shadow = $style['shadow'];
            $css .= sprintf(
                'text-shadow:%spx %spx %spx %s;',
                $shadow['offsetX'] ?? 2,
                $shadow['offsetY'] ?? 2,
                $shadow['blur'] ?? 4,
                e($shadow['color'] ?? '#00000066'),
            );
        }

        if (is_array($style['outline'] ?? null)) {
            $outline = $style['outline'];
            $css .= sprintf('-webkit-text-stroke:%spx %s;paint-order:stroke fill;', $outline['width'] ?? 2, e($outline['color'] ?? '#151c27'));
        }

        if (is_array($style['highlight'] ?? null)) {
            $css .= 'background-color:'.e($style['highlight']['color'] ?? '#fff59d').';box-decoration-break:clone;-webkit-box-decoration-break:clone;padding:0.05em 0.2em;border-radius:0.15em;';
        }

        return $css;
    }

    private function wrap(string $style, string $inner): string
    {
        return "<div style=\"{$style}\">{$inner}</div>";
    }
}
