<?php

namespace Tests\Unit;

use App\Services\LayoutToHtmlRenderer;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LayoutToHtmlRendererTest extends TestCase
{
    public function test_a_bound_text_element_renders_the_placeholder_token_not_the_binding_name(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('{recipient_name}', $html);
    }

    public function test_a_static_text_element_renders_its_literal_content_escaped(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => null, 'content' => '<script>alert(1)</script>', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_qrcode_and_signature_elements_render_their_placeholder_tokens(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'qrcode', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
                ['id' => 'el_2', 'type' => 'signature', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 1],
            ],
        ]);

        $this->assertStringContainsString('{qrcode}', $html);
        $this->assertStringContainsString('{signature}', $html);
    }

    public function test_elements_render_in_z_order(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'second', 'type' => 'text', 'binding' => null, 'content' => 'SECOND', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 1],
                ['id' => 'first', 'type' => 'text', 'binding' => null, 'content' => 'FIRST', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertLessThan(strpos($html, 'SECOND'), strpos($html, 'FIRST'));
    }

    public function test_position_and_size_are_expressed_as_percentages_in_the_output(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => null, 'content' => 'X', 'xPercent' => 12.5, 'yPercent' => 33.3, 'widthPercent' => 40, 'heightPercent' => 8, 'rotation' => 15, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('left:12.5%', $html);
        $this->assertStringContainsString('top:33.3%', $html);
        $this->assertStringContainsString('rotate(15deg)', $html);
    }

    public function test_background_html_is_preserved_and_elements_are_injected_before_closing_body(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'background_html' => '<!DOCTYPE html><html><head><style>.border{border:1px solid red}</style></head><body><div class="border">Decorative</div></body></html>',
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('class="border"', $html);
        $this->assertStringContainsString('Decorative', $html);
        $this->assertStringContainsString('{recipient_name}', $html);
        $this->assertLessThan(strpos($html, '{recipient_name}'), strpos($html, 'Decorative'));
        $this->assertSame(1, substr_count($html, '</body>'));
        $this->assertSame(1, substr_count($html, '<body'));
    }

    public function test_no_background_html_falls_back_to_the_original_bare_document(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => null, 'content' => 'X', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertSame(1, substr_count($html, '<body'));
    }

    public function test_a_background_color_renders_as_css_background_color(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'background' => ['type' => 'color', 'value' => '#ff00ff'],
            'elements' => [],
        ]);

        $this->assertStringContainsString('background-color:#ff00ff;', $html);
    }

    public function test_a_background_image_is_inlined_as_a_data_uri_from_the_public_disk(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('templates/1/assets/bg.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $html = (new LayoutToHtmlRenderer)->render([
            'background' => ['type' => 'image', 'value' => 'templates/1/assets/bg.png'],
            'elements' => [],
        ]);

        $this->assertStringContainsString('background-image:url(data:image/png;base64,', $html);
        $this->assertStringContainsString('background-size:cover;', $html);
    }

    public function test_a_missing_background_image_falls_back_to_white_instead_of_erroring(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $html = (new LayoutToHtmlRenderer)->render([
            'background' => ['type' => 'image', 'value' => 'templates/1/assets/missing.png'],
            'elements' => [],
        ]);

        $this->assertStringContainsString('background-color:#ffffff;', $html);
        $this->assertStringNotContainsString('background-image', $html);
    }

    public function test_a_decorative_unbound_image_element_is_inlined_as_a_data_uri(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('templates/1/assets/seal.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='
        ));

        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'image', 'binding' => null, 'src' => 'templates/1/assets/seal.png', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('<img src="data:image/png;base64,', $html);
    }

    public function test_a_bound_image_element_still_renders_its_placeholder_token(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'image', 'binding' => 'course_logo', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
            ],
        ]);

        $this->assertStringContainsString('{course_logo}', $html);
    }

    public function test_a_rect_shape_still_renders_as_a_plain_bordered_box(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => 'rect', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0, 'style' => ['fill' => '#ff0000', 'stroke' => '#000000', 'strokeWidth' => 2]],
            ],
        ]);

        $this->assertStringContainsString('background:#ff0000;', $html);
        $this->assertStringNotContainsString('clip-path', $html);
    }

    /**
     * Regression test for a real bug: the builder creates "Line" shapes as
     * a plain filled Fabric.Rect (fill color, strokeWidth explicitly 0 —
     * see certificate-builder.js addShape('line')), NOT as an actual
     * stroked line. A renderer that draws 'line' via border/stroke instead
     * of fill renders every builder-created line completely invisible,
     * since strokeWidth is always 0 for them.
     */
    public function test_a_line_shape_with_the_builders_real_world_default_style_is_visible(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => 'line', 'xPercent' => 10, 'yPercent' => 10, 'widthPercent' => 30, 'heightPercent' => 0.5, 'rotation' => 0, 'z' => 0, 'style' => ['fill' => '#151c27', 'stroke' => '#151c27', 'strokeWidth' => 0]],
            ],
        ]);

        $this->assertStringContainsString('background:#151c27;', $html);
    }

    public function test_a_circle_shape_renders_with_border_radius_instead_of_clip_path(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => 'circle', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 10, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0, 'style' => []],
            ],
        ]);

        $this->assertStringContainsString('border-radius:50%;', $html);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function polygonShapeKindsProvider(): array
    {
        return [
            'triangle' => ['triangle'],
            'diamond' => ['diamond'],
            'pentagon' => ['pentagon'],
            'hexagon' => ['hexagon'],
            'star' => ['star'],
            'seal' => ['seal'],
            'arrow' => ['arrow'],
            'banner' => ['banner'],
            'heart' => ['heart'],
        ];
    }

    #[DataProvider('polygonShapeKindsProvider')]
    public function test_every_expanded_shape_kind_renders_a_clip_path_polygon_not_a_plain_rectangle(string $shapeKind): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => $shapeKind, 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 20, 'heightPercent' => 20, 'rotation' => 0, 'z' => 0, 'style' => ['fill' => '#00ff00']],
            ],
        ]);

        $this->assertStringContainsString('clip-path:polygon(', $html);
    }

    public function test_a_shape_carries_opacity_and_flip_into_a_single_transform_declaration(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'shape', 'shapeKind' => 'star', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 20, 'heightPercent' => 20, 'rotation' => 10, 'z' => 0, 'style' => ['fill' => '#00ff00', 'opacity' => 0.5, 'flipX' => true]],
            ],
        ]);

        $this->assertStringContainsString('opacity:0.5;', $html);
        $this->assertStringContainsString('transform:rotate(10deg) scaleX(-1);', $html);
        $this->assertSame(1, substr_count($html, 'transform:'));
    }

    public function test_text_effects_render_as_css_on_an_inner_span(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                [
                    'id' => 'el_1', 'type' => 'text', 'binding' => null, 'content' => 'Styled', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0,
                    'style' => [
                        'fontStyle' => 'italic', 'underline' => true, 'linethrough' => true,
                        // Fabric charSpacing=100 at 20px → 2px (see CertificateTextMetrics).
                        'fontSize' => 20, 'letterSpacing' => 100, 'lineHeight' => 1.5, 'opacity' => 0.8,
                        'shadow' => ['color' => '#000000', 'blur' => 4, 'offsetX' => 2, 'offsetY' => 2],
                        'outline' => ['color' => '#151c27', 'width' => 2],
                        'highlight' => ['color' => '#fff59d'],
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('font-style:italic;', $html);
        $this->assertStringContainsString('underline line-through', $html);
        $this->assertStringContainsString('letter-spacing:2px;', $html);
        $this->assertStringContainsString('line-height:1.5;', $html);
        $this->assertStringContainsString('opacity:0.8;', $html);
        $this->assertStringContainsString('text-shadow:2px 2px 4px #000000;', $html);
        $this->assertStringContainsString('-webkit-text-stroke:2px #151c27;', $html);
        $this->assertStringContainsString('background-color:#fff59d;', $html);
        $this->assertStringContainsString('<span style="', $html);
        $this->assertStringContainsString('>Styled</span>', $html);
    }

    public function test_a_text_gradient_fill_renders_as_a_clipped_background_gradient(): void
    {
        $html = (new LayoutToHtmlRenderer)->render([
            'elements' => [
                ['id' => 'el_1', 'type' => 'text', 'binding' => null, 'content' => 'Gradient', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0, 'style' => ['gradient' => ['from' => '#b40012', 'to' => '#f59e0b']]],
            ],
        ]);

        $this->assertStringContainsString('linear-gradient(90deg,#b40012,#f59e0b)', $html);
        $this->assertStringContainsString('-webkit-background-clip:text;', $html);
        $this->assertStringContainsString('color:transparent;', $html);
    }
}
