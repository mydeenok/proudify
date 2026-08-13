<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Imagick;
use RuntimeException;
use Tests\TestCase;

/**
 * End-to-end proof that the Chrome-free canvas painter works: real Node +
 * @napi-rs/canvas process, real font decode, real Imagick PDF wrap - no
 * mocks. Requires `node` on PATH with @napi-rs/canvas installed.
 */
class CertificateCanvasRenderDriverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_a_pdf_at_the_correct_page_pixel_dimensions_with_text_and_a_qr_image(): void
    {
        Storage::fake('local');

        $qrPath = UploadedFile::fake()->image('qr.png', 300, 300)->store('certificates/qr', 'local');

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'elements' => [
                    [
                        'type' => 'text',
                        'binding' => 'recipient_name',
                        'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 80, 'heightPercent' => 10,
                        'z' => 1,
                        'style' => ['fontFamily' => 'Inter', 'fontSize' => 32, 'fontWeight' => '700', 'color' => '#151c27', 'textAlign' => 'center'],
                    ],
                    [
                        'type' => 'qrcode',
                        'xPercent' => 5, 'yPercent' => 80, 'widthPercent' => 12, 'heightPercent' => 12,
                        'z' => 2,
                    ],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'qr_code_path' => $qrPath,
        ]);

        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $this->assertTrue(Storage::disk('local')->exists($pdfPath));
        $this->assertGreaterThan(0, Storage::disk('local')->size($pdfPath));

        $imagick = new Imagick;
        $imagick->setResolution(300, 300);
        $imagick->readImage(Storage::disk('local')->path($pdfPath).'[0]');

        // a4 landscape @ 300dpi - see CertificateCanvasRenderService::PAGE_PIXELS_AT_300DPI.
        $this->assertSame(3508, $imagick->getImageWidth());
        $this->assertSame(2480, $imagick->getImageHeight());

        $imagick->clear();
        $imagick->destroy();
    }

    public function test_it_supports_portrait_letter_pages_too(): void
    {
        Storage::fake('local');

        $template = Template::factory()->create([
            'page_format' => 'letter',
            'orientation' => 'portrait',
            'canvas_json' => [
                'elements' => [
                    ['type' => 'text', 'content' => 'Static heading', 'xPercent' => 0, 'yPercent' => 0, 'widthPercent' => 100, 'heightPercent' => 10, 'style' => ['textAlign' => 'center']],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $imagick = new Imagick;
        $imagick->setResolution(300, 300);
        $imagick->readImage(Storage::disk('local')->path($pdfPath).'[0]');

        // letter portrait @ 300dpi.
        $this->assertSame(2550, $imagick->getImageWidth());
        $this->assertSame(3300, $imagick->getImageHeight());

        $imagick->clear();
        $imagick->destroy();
    }

    /**
     * Reproduces the real admin path end-to-end: TemplateController::store()
     * with html_content left blank (see the "New Template" form's now-
     * optional HTML field), then a builder autosave exactly like the
     * front-end sends it (canvas_json only, no background_html - the client
     * never sends that key). Confirms the two pieces wired together in this
     * change actually make a from-scratch builder template eligible for the
     * Chrome-free driver, not just hand-crafted canvas_json in a factory.
     */
    public function test_a_template_created_blank_and_designed_in_the_builder_renders_via_canvas(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.templates.store'), [
            'name' => 'Blank Builder Template',
            'page_format' => 'a4',
            'orientation' => 'landscape',
            // html_content intentionally omitted.
        ])->assertRedirect(route('admin.templates.builder', $template = Template::where('name', 'Blank Builder Template')->firstOrFail()));

        $this->assertSame('', $template->html_content);

        $this->actingAs($admin)->postJson(route('admin.templates.builder.save', $template), [
            'canvas_json' => [
                'elements' => [
                    [
                        'type' => 'text',
                        'binding' => 'recipient_name',
                        'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 80, 'heightPercent' => 10,
                        'z' => 1,
                        'style' => ['fontFamily' => 'Inter', 'fontSize' => 32, 'textAlign' => 'center'],
                    ],
                ],
            ],
            'version' => $template->version,
        ])->assertOk();

        $template->refresh();
        $this->assertArrayNotHasKey(
            'background_html',
            $template->canvas_json,
            'A blank html_content at creation must never seed background_html on first save.'
        );

        $certificate = Certificate::factory()->create(['template_id' => $template->id]);
        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        // Proves the canvas driver (not a Browsershot fallback) actually
        // produced this file: exact 300dpi a4-landscape pixel dimensions,
        // same assertion style as the other tests in this class.
        $imagick = new Imagick;
        $imagick->setResolution(300, 300);
        $imagick->readImage(Storage::disk('local')->path($pdfPath).'[0]');

        $this->assertSame(3508, $imagick->getImageWidth());
        $this->assertSame(2480, $imagick->getImageHeight());

        $imagick->clear();
        $imagick->destroy();
    }

    /**
     * Issuance is canvas-only — an incompatible template must fail loudly,
     * never silently need Chromium. See CertificateRenderService::renderPdf().
     */
    public function test_an_incompatible_template_throws_instead_of_silently_falling_back_to_chrome(): void
    {
        // Hand-written HTML, no canvas_json at all.
        $template = Template::factory()->create(['html_content' => '<h1>{title}</h1>']);
        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("isn't canvas-compatible");

        app(CertificateRenderService::class)->renderPdf($certificate);
    }

    /**
     * Regression test for a real cross-renderer bug: the builder creates
     * "Line" shapes as a plain filled Fabric.Rect with strokeWidth forced
     * to 0 (see certificate-builder.js addShape('line')) - fill is what
     * makes it visible, not a stroke. certificate-canvas-render.mjs used
     * to draw 'line' via ctx.stroke() only, so every builder-created line
     * (strokeWidth always 0) rendered as literally nothing. This samples
     * an actual pixel where the line sits to prove it's really painted,
     * not just that the PDF happens to exist.
     */
    public function test_a_line_shape_with_the_builders_real_world_default_style_is_actually_painted(): void
    {
        Storage::fake('local');

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'elements' => [
                    [
                        'type' => 'shape',
                        'shapeKind' => 'line',
                        'xPercent' => 10, 'yPercent' => 48, 'widthPercent' => 80, 'heightPercent' => 4,
                        'z' => 0,
                        // Exactly the defaults certificate-builder.js assigns:
                        // fill carries the color, strokeWidth is always 0.
                        'style' => ['fill' => '#ff0000', 'stroke' => '#ff0000', 'strokeWidth' => 0],
                    ],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create(['template_id' => $template->id]);
        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $imagick = new Imagick;
        $imagick->setResolution(300, 300);
        $imagick->readImage(Storage::disk('local')->path($pdfPath).'[0]');

        // a4 landscape @ 300dpi is 3508x2480 (see the dimension tests
        // above) - sample the centre of the line's box (x 10-90%, y 48-52%).
        $pixel = $imagick->getImagePixelColor((int) (3508 * 0.5), (int) (2480 * 0.5));
        $colors = $pixel->getColor();

        $imagick->clear();
        $imagick->destroy();

        $this->assertGreaterThan(150, $colors['r'], 'the red line fill should be visible at its own centre pixel, not blank white');
        $this->assertLessThan(100, $colors['g']);
        $this->assertLessThan(100, $colors['b']);
    }

    public function test_it_renders_shapes_and_a_colored_background_without_chrome(): void
    {
        Storage::fake('local');

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'background' => ['type' => 'color', 'value' => '#fff8f0'],
                'elements' => [
                    [
                        'type' => 'shape',
                        'shapeKind' => 'rect',
                        'xPercent' => 2, 'yPercent' => 2, 'widthPercent' => 96, 'heightPercent' => 96,
                        'z' => 0,
                        'style' => ['fill' => 'transparent', 'stroke' => '#b40012', 'strokeWidth' => 8],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Shape Cert',
                        'xPercent' => 10, 'yPercent' => 40, 'widthPercent' => 80, 'heightPercent' => 10,
                        'z' => 1,
                        'style' => ['fontFamily' => 'Inter', 'fontSize' => 28, 'textAlign' => 'center'],
                    ],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create(['template_id' => $template->id]);
        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $this->assertTrue(Storage::disk('local')->exists($pdfPath));
        $template->refresh();
        $this->assertNotNull($template->watermark_corner);
    }

    public function test_it_renders_every_expanded_shape_and_vector_kind_without_chrome(): void
    {
        Storage::fake('local');

        $shapeKinds = ['triangle', 'diamond', 'pentagon', 'hexagon', 'star', 'arrow', 'heart', 'seal', 'banner'];

        $elements = [];
        $z = 0;

        foreach ($shapeKinds as $index => $shapeKind) {
            $elements[] = [
                'type' => 'shape',
                'shapeKind' => $shapeKind,
                'xPercent' => ($index % 3) * 30 + 2,
                'yPercent' => intdiv($index, 3) * 30 + 2,
                'widthPercent' => 25,
                'heightPercent' => 25,
                'z' => $z++,
                'style' => [
                    'fill' => '#e0f2f1',
                    'stroke' => '#b40012',
                    'strokeWidth' => 3,
                    'opacity' => 0.85,
                    'flipX' => $index % 2 === 0,
                ],
            ];
        }

        $elements[] = [
            'type' => 'text',
            'content' => 'Styled Text',
            'xPercent' => 10, 'yPercent' => 90, 'widthPercent' => 80, 'heightPercent' => 8,
            'z' => $z++,
            'style' => [
                'fontFamily' => 'Montserrat',
                'fontSize' => 22,
                'fontWeight' => '700',
                'fontStyle' => 'italic',
                'underline' => true,
                'textAlign' => 'center',
                'opacity' => 0.9,
            ],
        ];

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => $elements,
            ],
        ]);

        $certificate = Certificate::factory()->create(['template_id' => $template->id]);
        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $this->assertTrue(Storage::disk('local')->exists($pdfPath));
    }

    public function test_it_renders_every_expanded_text_effect_without_chrome(): void
    {
        Storage::fake('local');

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => [
                    [
                        'type' => 'text',
                        'content' => 'Spaced Out Heading',
                        'xPercent' => 5, 'yPercent' => 5, 'widthPercent' => 90, 'heightPercent' => 12,
                        'z' => 0,
                        'style' => ['fontFamily' => 'Cinzel', 'fontSize' => 30, 'letterSpacing' => 120, 'lineHeight' => 1.4, 'textAlign' => 'center'],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Shadowed Title',
                        'xPercent' => 5, 'yPercent' => 20, 'widthPercent' => 90, 'heightPercent' => 10,
                        'z' => 1,
                        'style' => [
                            'fontSize' => 24, 'textAlign' => 'center',
                            'shadow' => ['color' => '#00000080', 'blur' => 6, 'offsetX' => 3, 'offsetY' => 3],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Outlined Text',
                        'xPercent' => 5, 'yPercent' => 32, 'widthPercent' => 90, 'heightPercent' => 10,
                        'z' => 2,
                        'style' => [
                            'fontSize' => 24, 'fontWeight' => '700', 'textAlign' => 'center', 'color' => '#ffffff',
                            'outline' => ['color' => '#151c27', 'width' => 3],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Highlighted Text',
                        'xPercent' => 5, 'yPercent' => 44, 'widthPercent' => 90, 'heightPercent' => 10,
                        'z' => 3,
                        'style' => [
                            'fontSize' => 24, 'textAlign' => 'center',
                            'highlight' => ['color' => '#fff59d'],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Gradient Fill Text',
                        'xPercent' => 5, 'yPercent' => 56, 'widthPercent' => 90, 'heightPercent' => 10,
                        'z' => 4,
                        'style' => [
                            'fontSize' => 24, 'fontWeight' => '700', 'textAlign' => 'center',
                            'gradient' => ['from' => '#b40012', 'to' => '#f59e0b'],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'content' => 'Strikethrough Text',
                        'xPercent' => 5, 'yPercent' => 68, 'widthPercent' => 90, 'heightPercent' => 10,
                        'z' => 5,
                        'style' => ['fontSize' => 24, 'textAlign' => 'center', 'linethrough' => true],
                    ],
                    [
                        // A very long value in a tiny box: with autoFit off this
                        // would badly overflow/wrap — this just proves the
                        // shrink-to-fit path runs without crashing or looping forever.
                        'type' => 'text',
                        'binding' => 'recipient_name',
                        'xPercent' => 5, 'yPercent' => 80, 'widthPercent' => 40, 'heightPercent' => 8,
                        'z' => 6,
                        'style' => ['fontSize' => 40, 'autoFit' => true],
                    ],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'recipient_name' => 'A Very Long Recipient Name That Would Otherwise Overflow The Box',
        ]);
        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $this->assertTrue(Storage::disk('local')->exists($pdfPath));
    }

    public function test_an_image_element_bound_to_company_logo_uses_issuer_org_logos_not_custom_image_fields(): void
    {
        Storage::fake('local');

        // Solid red 80x80 PNG — easy to spot when sampling inside the logo box.
        $logoPath = 'organization-logos/red-logo.png';
        $imagickLogo = new Imagick;
        $imagickLogo->newImage(80, 80, new \ImagickPixel('#ff0000'));
        $imagickLogo->setImageFormat('png');
        Storage::disk('local')->put($logoPath, $imagickLogo->getImageBlob());
        $imagickLogo->clear();
        $imagickLogo->destroy();

        $user = User::factory()->create(['org_logos' => [$logoPath]]);

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'canvas_json' => [
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => [
                    [
                        // Historical builder pattern: type=image + binding=company_logo
                        // (slugified "Company Logo" custom-image label). Must resolve
                        // from company_logos, not custom_image_fields.
                        'type' => 'image',
                        'binding' => 'company_logo',
                        'xPercent' => 80, 'yPercent' => 5, 'widthPercent' => 15, 'heightPercent' => 15,
                        'z' => 1,
                    ],
                ],
            ],
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'user_id' => $user->id,
            'company_logos' => [$logoPath],
            'custom_image_fields' => [],
        ]);

        $pdfPath = app(CertificateRenderService::class)->renderPdf($certificate);

        $imagick = new Imagick;
        $imagick->setResolution(300, 300);
        $imagick->readImage(Storage::disk('local')->path($pdfPath).'[0]');

        // Sample near the centre of the logo box (≈87.5% x, 12.5% y).
        $pixel = $imagick->getImagePixelColor(
            (int) (3508 * 0.875),
            (int) (2480 * 0.125),
        );
        $colors = $pixel->getColor();

        $imagick->clear();
        $imagick->destroy();

        $this->assertGreaterThan(150, $colors['r'], 'company logo fill should be visible in the issued PDF');
        $this->assertLessThan(100, $colors['g']);
        $this->assertLessThan(100, $colors['b']);
    }

    public function test_live_preview_for_a_canvas_template_returns_png_from_the_same_painter(): void
    {
        Storage::fake('local');

        $signaturePath = UploadedFile::fake()->image('sig.png')->store('signatures', 'local');
        $logoPath = UploadedFile::fake()->image('logo.png')->store('organization-logos', 'local');

        $user = User::factory()->create([
            'signature_path' => $signaturePath,
            'org_logos' => [$logoPath],
        ]);

        $template = Template::factory()->create([
            'page_format' => 'a4',
            'orientation' => 'landscape',
            'is_active' => true,
            'canvas_json' => [
                'background' => ['type' => 'color', 'value' => '#ffffff'],
                'elements' => [
                    [
                        'type' => 'text',
                        'binding' => 'title',
                        'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 80, 'heightPercent' => 10,
                        'z' => 0,
                        'style' => ['fontFamily' => 'Montserrat', 'fontSize' => 24, 'letterSpacing' => 20, 'textAlign' => 'center'],
                    ],
                    [
                        'type' => 'image',
                        'binding' => 'company_logo',
                        'xPercent' => 80, 'yPercent' => 5, 'widthPercent' => 15, 'heightPercent' => 15,
                        'z' => 1,
                    ],
                    [
                        'type' => 'signature',
                        'xPercent' => 60, 'yPercent' => 70, 'widthPercent' => 20, 'heightPercent' => 12,
                        'z' => 2,
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($user)->post(route('certificates.preview.render'), [
            'template_id' => $template->id,
            'title' => 'Canvas Preview Title',
            'recipient_name' => 'Preview Person',
        ]);

        $response->assertOk();
        $this->assertSame('canvas', $response->headers->get('X-Preview-Mode'));
        $this->assertStringStartsWith('image/png', (string) $response->headers->get('Content-Type'));
        $this->assertSame("\x89PNG", substr($response->getContent(), 0, 4));
        $this->assertGreaterThan(1000, strlen($response->getContent()));
    }
}
