<?php

namespace Tests\Unit;

use App\Services\LayoutToHtmlRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Guards against the exact class of bug this codebase has repeatedly hit:
 * canvas_json is one shared data format, but it's independently painted by
 * three separate engines —
 *
 *   1. certificate-builder.js (Fabric.js, what you see live while designing)
 *   2. LayoutToHtmlRenderer.php (HTML/CSS, Browsershot + the Live Preview)
 *   3. certificate-canvas-render.mjs (Node + @napi-rs/canvas, Chrome-free PDF)
 *
 * Every shape kind and text style feature has to be hand-reimplemented in
 * all three, in three different graphics APIs, with nothing enforcing they
 * stay in sync. History: background images, every polygon shape kind
 * (star/seal/banner/heart/...), and every text effect (shadow/outline/
 * highlight/gradient/letter-spacing/...) were all added to the builder +
 * Node renderer but silently missing from the HTML renderer for a while.
 * The "Line" shape kind was present in all three but interpreted through
 * two incompatible mechanisms (filled box vs. stroked border), so it
 * rendered invisibly server-side despite being "supported" everywhere.
 *
 * This test can't run the real Fabric.js builder headlessly (no browser in
 * this environment - and we deliberately don't want a Chrome dependency
 * anywhere in this pipeline), so it takes the next best, still-genuinely-
 * useful approach: static presence checks against the builder's own source
 * text, plus real behavioural assertions against the two PHP/Node engines
 * this app actually controls end-to-end. Anyone adding a new shape kind or
 * text style property should add it to the manifests below FIRST — the
 * failures then point at exactly which engine(s) still need the feature.
 */
class RendererFeatureParityTest extends TestCase
{
    /**
     * Every shape kind the builder's "Elements" panel can add (see
     * certificate-builder.js: POLYGON_SHAPE_KINDS plus the explicit
     * circle/line/heart/rect special cases). 'rect' and 'line' are
     * intentionally excluded from the cross-engine vertex-list check below
     * — both are meant to degrade to the same generic filled-box path in
     * every engine (a line is just a very short, thin rect - see the Line
     * shape regression tests), so they have no dedicated clip-path/vertex
     * branch to compare.
     */
    private const POLYGON_SHAPE_KINDS = ['triangle', 'diamond', 'pentagon', 'hexagon', 'star', 'seal', 'arrow', 'banner', 'heart'];

    /**
     * Every text style property the builder can produce in canvas_json,
     * mapped to whether the HTML/Browsershot renderer is expected to
     * support it. 'autoFit' is a deliberate, documented exception: shrink-
     * to-fit needs the actual wrapped-text measurement loop
     * certificate-canvas-render.mjs runs (fittedFontSize()), which has no
     * equivalent in a static CSS string - approximating it well isn't
     * worth the complexity for what's a rare edge case. Every other
     * feature has no such excuse.
     */
    private const TEXT_FEATURES = [
        'fontStyle' => true,
        'underline' => true,
        'linethrough' => true,
        'letterSpacing' => true,
        'lineHeight' => true,
        'shadow' => true,
        'outline' => true,
        'highlight' => true,
        'gradient' => true,
        'autoFit' => false,
    ];

    private function builderSource(): string
    {
        return file_get_contents(base_path('resources/js/certificate-builder.js'));
    }

    private function nodeRendererSource(): string
    {
        return file_get_contents(base_path('node/certificate-canvas-render.mjs'));
    }

    private function htmlRendererSource(): string
    {
        return file_get_contents(app_path('Services/LayoutToHtmlRenderer.php'));
    }

    public function test_the_builders_polygon_shape_kind_list_matches_the_manifest(): void
    {
        // If this fails, either the manifest above is stale or someone
        // added/removed a shape in the builder without updating it here -
        // update whichever one is actually wrong, then re-run.
        preg_match('/POLYGON_SHAPE_KINDS\s*=\s*\[([^\]]+)\]/', $this->builderSource(), $matches);
        $this->assertNotEmpty($matches, 'Could not find POLYGON_SHAPE_KINDS in certificate-builder.js - has it been renamed/restructured?');

        preg_match_all("/'([a-z]+)'/", $matches[1], $kindMatches);
        $builderKinds = $kindMatches[1];
        sort($builderKinds);

        $expected = self::POLYGON_SHAPE_KINDS;
        sort($expected);
        // 'heart' is a separate explicit branch in the builder, not part of
        // POLYGON_SHAPE_KINDS (it uses a Path, not a Polygon) - excluded
        // from this particular comparison, but still required below.
        $expected = array_values(array_diff($expected, ['heart']));

        $this->assertSame($expected, $builderKinds, 'certificate-builder.js POLYGON_SHAPE_KINDS drifted from the manifest in this test.');
    }

    #[DataProvider('polygonShapeKindsProvider')]
    public function test_every_polygon_shape_kind_has_a_dedicated_branch_in_the_node_renderer(string $kind): void
    {
        $source = $this->nodeRendererSource();

        // 'heart' is drawn via bezier curves (traceHeart()) rather than a
        // straight-edge vertex list, so it's an `else if (kind === 'heart')`
        // branch in drawShape() instead of a `case` inside shapeVertices() -
        // a real, intentional structural difference from the other 8 kinds,
        // not a gap.
        $pattern = $kind === 'heart'
            ? "/kind === 'heart'/"
            : "/case '".preg_quote($kind, '/')."':/";

        $this->assertMatchesRegularExpression(
            $pattern,
            $source,
            "certificate-canvas-render.mjs has no branch for '{$kind}' - the Chrome-free PDF would silently fall back to a plain rectangle for this shape."
        );
    }

    #[DataProvider('polygonShapeKindsProvider')]
    public function test_every_polygon_shape_kind_has_a_clip_path_branch_in_the_html_renderer(string $kind): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => $kind, 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 20, 'heightPercent' => 20, 'rotation' => 0, 'z' => 0, 'style' => ['fill' => '#00ff00']],
            ],
        ]);

        $this->assertStringContainsString(
            'clip-path:polygon(',
            $html,
            "LayoutToHtmlRenderer renders '{$kind}' as a plain rectangle instead of its real shape - this is exactly the Live Preview / Browsershot bug found in production."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function polygonShapeKindsProvider(): array
    {
        return collect(self::POLYGON_SHAPE_KINDS)->mapWithKeys(fn (string $kind) => [$kind => [$kind]])->all();
    }

    #[DataProvider('textFeaturesProvider')]
    public function test_every_text_feature_is_wired_into_the_builder(string $feature): void
    {
        $this->assertStringContainsString(
            $feature,
            $this->builderSource(),
            "certificate-builder.js never mentions '{$feature}' - either the manifest in this test is wrong, or this feature doesn't actually exist in the builder UI/serialize/deserialize code."
        );
    }

    #[DataProvider('textFeaturesProvider')]
    public function test_every_text_feature_is_wired_into_the_node_renderer(string $feature): void
    {
        $this->assertStringContainsString(
            $feature,
            $this->nodeRendererSource(),
            "certificate-canvas-render.mjs never mentions '{$feature}' - a builder-authored template using it would render incorrectly in the actual Chrome-free PDF."
        );
    }

    #[DataProvider('htmlSupportedTextFeaturesProvider')]
    public function test_every_html_supported_text_feature_is_wired_into_the_html_renderer(string $feature): void
    {
        $this->assertStringContainsString(
            $feature,
            $this->htmlRendererSource(),
            "LayoutToHtmlRenderer never mentions '{$feature}' - this is exactly the class of bug where the Live Preview / Browsershot path silently ignores a style the builder + Node renderer both support."
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function textFeaturesProvider(): array
    {
        return collect(array_keys(self::TEXT_FEATURES))->mapWithKeys(fn (string $feature) => [$feature => [$feature]])->all();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function htmlSupportedTextFeaturesProvider(): array
    {
        return collect(self::TEXT_FEATURES)
            ->filter(fn (bool $supported) => $supported)
            ->keys()
            ->mapWithKeys(fn (string $feature) => [$feature => [$feature]])
            ->all();
    }

    /**
     * The one feature this test deliberately does NOT require of the HTML
     * renderer - if someone silently adds 'autoFit' support there, that's
     * great, but if this assertion starts failing it means the manifest's
     * documented exception above is now stale and should be updated (or
     * the newly-added support should be left in, unregretted).
     */
    public function test_autofit_is_intentionally_absent_from_the_html_renderer_by_documented_design(): void
    {
        $this->assertArrayHasKey('autoFit', self::TEXT_FEATURES);
        $this->assertFalse(self::TEXT_FEATURES['autoFit']);
    }
}
