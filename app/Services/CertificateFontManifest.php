<?php

namespace App\Services;

/**
 * Single source of truth for every font family/weight the certificate
 * builder's font picker offers (see the <select id="prop-font-family">
 * options in resources/views/admin/templates/builder.blade.php and the
 * matching @fontsource imports in resources/js/certificate-builder.js).
 *
 * Both certificate-rendering engines this app controls need this same list:
 *   - CertificateCanvasRenderService decompresses each .woff2 to .ttf for
 *     Skia (@napi-rs/canvas has no woff2 support) and registers it by path.
 *   - LayoutToHtmlRenderer embeds each .woff2 as a base64 @font-face rule
 *     so Chrome can render the font inside an isolated `srcdoc` iframe
 *     (Live Preview / /certificates/preview), which has no access to
 *     anything the builder page itself loaded.
 *
 * Keeping this as one shared list - rather than two copies that drift, the
 * exact bug class this app has repeatedly hit for shapes/text effects -
 * means adding a font once here makes it available (and visually correct)
 * everywhere at once.
 */
class CertificateFontManifest
{
    /**
     * @return array<int, array{family: string, weight: int, file: string, pkg: string}>
     */
    public static function all(): array
    {
        return [
            ['family' => 'Inter', 'weight' => 400, 'file' => 'inter-latin-400-normal.woff2', 'pkg' => '@fontsource/inter'],
            ['family' => 'Inter', 'weight' => 700, 'file' => 'inter-latin-700-normal.woff2', 'pkg' => '@fontsource/inter'],
            ['family' => 'Noto Sans', 'weight' => 400, 'file' => 'noto-sans-latin-400-normal.woff2', 'pkg' => '@fontsource/noto-sans'],
            ['family' => 'Noto Sans', 'weight' => 700, 'file' => 'noto-sans-latin-700-normal.woff2', 'pkg' => '@fontsource/noto-sans'],
            ['family' => 'Playfair Display', 'weight' => 400, 'file' => 'playfair-display-latin-400-normal.woff2', 'pkg' => '@fontsource/playfair-display'],
            ['family' => 'Playfair Display', 'weight' => 700, 'file' => 'playfair-display-latin-700-normal.woff2', 'pkg' => '@fontsource/playfair-display'],
            // Calibri-metric-compatible free substitute (Calibri itself is not
            // freely redistributable).
            ['family' => 'Carlito', 'weight' => 400, 'file' => 'carlito-latin-400-normal.woff2', 'pkg' => '@fontsource/carlito'],
            ['family' => 'Carlito', 'weight' => 700, 'file' => 'carlito-latin-700-normal.woff2', 'pkg' => '@fontsource/carlito'],
            // Classic engraved-look serif, popular for certificate titles.
            ['family' => 'Cinzel', 'weight' => 400, 'file' => 'cinzel-latin-400-normal.woff2', 'pkg' => '@fontsource/cinzel'],
            ['family' => 'Cinzel', 'weight' => 700, 'file' => 'cinzel-latin-700-normal.woff2', 'pkg' => '@fontsource/cinzel'],
            // Elegant serif for body copy / sub-headings.
            ['family' => 'Cormorant Garamond', 'weight' => 400, 'file' => 'cormorant-garamond-latin-400-normal.woff2', 'pkg' => '@fontsource/cormorant-garamond'],
            ['family' => 'Cormorant Garamond', 'weight' => 700, 'file' => 'cormorant-garamond-latin-700-normal.woff2', 'pkg' => '@fontsource/cormorant-garamond'],
            // Signature / calligraphy style fonts — only ship weight 400,
            // these families don't have a meaningful "bold" cut.
            ['family' => 'Dancing Script', 'weight' => 400, 'file' => 'dancing-script-latin-400-normal.woff2', 'pkg' => '@fontsource/dancing-script'],
            ['family' => 'Great Vibes', 'weight' => 400, 'file' => 'great-vibes-latin-400-normal.woff2', 'pkg' => '@fontsource/great-vibes'],
            ['family' => 'Pacifico', 'weight' => 400, 'file' => 'pacifico-latin-400-normal.woff2', 'pkg' => '@fontsource/pacifico'],
            // Popular clean geometric sans + a readable serif — rounds out
            // the picker with modern, MNC-standard design-tool staples.
            ['family' => 'Montserrat', 'weight' => 400, 'file' => 'montserrat-latin-400-normal.woff2', 'pkg' => '@fontsource/montserrat'],
            ['family' => 'Montserrat', 'weight' => 700, 'file' => 'montserrat-latin-700-normal.woff2', 'pkg' => '@fontsource/montserrat'],
            ['family' => 'Lora', 'weight' => 400, 'file' => 'lora-latin-400-normal.woff2', 'pkg' => '@fontsource/lora'],
            ['family' => 'Lora', 'weight' => 700, 'file' => 'lora-latin-700-normal.woff2', 'pkg' => '@fontsource/lora'],
            // Tamil glyphs as a dedicated family AND as the last Inter/Noto
            // fallback chain member so mixed Tamil+Latin text doesn't tofu.
            ['family' => 'Noto Sans Tamil', 'weight' => 400, 'file' => 'noto-sans-tamil-tamil-400-normal.woff2', 'pkg' => '@fontsource/noto-sans-tamil'],
            ['family' => 'Noto Sans Tamil', 'weight' => 700, 'file' => 'noto-sans-tamil-tamil-700-normal.woff2', 'pkg' => '@fontsource/noto-sans-tamil'],
        ];
    }

    public static function woff2Path(array $font): string
    {
        return base_path("node_modules/{$font['pkg']}/files/{$font['file']}");
    }
}
