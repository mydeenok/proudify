<?php

namespace Tests\Unit;

use App\Models\Certificate;
use App\Models\Template;
use App\Services\CertificateCanvasRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * supports() is the safety gate for the whole Chrome-free canvas driver -
 * it must only say "yes" for templates this renderer can faithfully
 * reproduce (pure canvas_json, no imported decorative HTML), and "no" for
 * everything else so those certificates silently keep using the proven
 * Browsershot path. These tests never touch Node/@napi-rs/canvas - see
 * tests/Feature/Certificates/CertificateCanvasRenderDriverTest.php for the
 * real end-to-end render.
 */
class CertificateCanvasRenderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_supports_a_pure_canvas_json_template_with_no_background_html(): void
    {
        $certificate = $this->certificateWithCanvasJson([
            'elements' => [
                ['type' => 'text', 'content' => 'Hello', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 50, 'heightPercent' => 10],
                ['type' => 'qrcode', 'xPercent' => 0, 'yPercent' => 50, 'widthPercent' => 10, 'heightPercent' => 10],
                ['type' => 'signature', 'xPercent' => 60, 'yPercent' => 50, 'widthPercent' => 20, 'heightPercent' => 10],
                ['type' => 'company_logo', 'xPercent' => 80, 'yPercent' => 0, 'widthPercent' => 15, 'heightPercent' => 10],
                ['type' => 'image', 'binding' => 'course_logo', 'xPercent' => 0, 'yPercent' => 80, 'widthPercent' => 15, 'heightPercent' => 10],
            ],
        ]);

        $this->assertTrue(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_does_not_support_a_template_with_no_canvas_json(): void
    {
        $certificate = $this->certificateWithCanvasJson(null);

        $this->assertFalse(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_does_not_support_a_template_with_empty_elements(): void
    {
        $certificate = $this->certificateWithCanvasJson(['elements' => []]);

        $this->assertFalse(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_does_not_support_a_template_with_imported_background_html(): void
    {
        // background_html carries arbitrary hand-authored decorative
        // markup/CSS (see TemplateBackgroundImportService) that this
        // renderer has no CSS engine to reproduce - it must defer to
        // Browsershot rather than risk a visually-wrong certificate.
        $certificate = $this->certificateWithCanvasJson([
            'elements' => [['type' => 'text', 'content' => 'Hello', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 50, 'heightPercent' => 10]],
            'background_html' => '<html><body style="border:4px solid red;">Imported design</body></html>',
        ]);

        $this->assertFalse(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_does_not_support_an_unrecognized_element_type(): void
    {
        $certificate = $this->certificateWithCanvasJson([
            'elements' => [['type' => 'video', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 50, 'heightPercent' => 10]],
        ]);

        $this->assertFalse(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_supports_shape_elements(): void
    {
        $certificate = $this->certificateWithCanvasJson([
            'elements' => [
                ['type' => 'shape', 'shapeKind' => 'rect', 'xPercent' => 5, 'yPercent' => 5, 'widthPercent' => 90, 'heightPercent' => 90, 'style' => ['stroke' => '#b40012', 'fill' => 'transparent']],
                ['type' => 'text', 'content' => 'Hello', 'xPercent' => 10, 'yPercent' => 40, 'widthPercent' => 80, 'heightPercent' => 10],
            ],
        ]);

        $this->assertTrue(app(CertificateCanvasRenderService::class)->supports($certificate));
    }

    public function test_geometry_corner_detection_picks_the_emptiest_corner(): void
    {
        $template = Template::factory()->create([
            'canvas_json' => [
                'elements' => [
                    // Fill three corners; leave bottom-left empty.
                    ['type' => 'text', 'content' => 'TL', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 30, 'heightPercent' => 10],
                    ['type' => 'text', 'content' => 'TR', 'xPercent' => 70, 'yPercent' => 0, 'widthPercent' => 30, 'heightPercent' => 10],
                    ['type' => 'text', 'content' => 'BR', 'xPercent' => 70, 'yPercent' => 90, 'widthPercent' => 30, 'heightPercent' => 10],
                ],
            ],
        ]);

        $this->assertSame(
            'bottom-left',
            app(CertificateCanvasRenderService::class)->detectEmptiestCornerFromCanvas($template)
        );
    }

    private function certificateWithCanvasJson(?array $canvasJson): Certificate
    {
        $template = Template::factory()->create(['canvas_json' => $canvasJson]);

        return Certificate::factory()->create(['template_id' => $template->id]);
    }
}
