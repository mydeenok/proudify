<?php

namespace Tests\Unit;

use App\Services\LayoutToHtmlRenderer;
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
}
