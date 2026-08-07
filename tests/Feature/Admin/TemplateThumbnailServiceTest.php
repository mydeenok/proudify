<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use App\Services\CertificateCanvasRenderService;
use App\Services\QrCodeService;
use App\Services\TemplateThumbnailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class TemplateThumbnailServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_a_png_thumbnail_for_canvas_templates(): void
    {
        Storage::fake('public');

        $template = Template::factory()->create([
            'thumbnail_path' => null,
            'canvas_json' => [
                'elements' => [
                    [
                        'type' => 'text',
                        'binding' => 'recipient_name',
                        'xPercent' => 10,
                        'yPercent' => 20,
                        'widthPercent' => 80,
                        'heightPercent' => 10,
                        'z' => 1,
                    ],
                ],
            ],
        ]);
        $issuer = User::factory()->create();

        $canvas = Mockery::mock(CertificateCanvasRenderService::class);
        $canvas->shouldReceive('supportsTemplate')->once()->with($template)->andReturn(true);
        $canvas->shouldReceive('renderPreviewPng')
            ->once()
            ->andReturn("\x89PNG\r\n\x1a\nfake-png");

        $service = new TemplateThumbnailService($canvas, app(QrCodeService::class));
        $path = $service->generateFromCanvas($template, $issuer);

        $this->assertSame("templates/{$template->id}/thumbnail.png", $path);
        Storage::disk('public')->assertExists($path);
        $this->assertSame($path, $template->fresh()->thumbnail_path);
    }

    public function test_it_skips_templates_the_canvas_renderer_does_not_support(): void
    {
        Storage::fake('public');

        $template = Template::factory()->create([
            'canvas_json' => [
                'background_html' => '<div>legacy</div>',
                'elements' => [
                    ['type' => 'text', 'binding' => 'title', 'xPercent' => 1, 'yPercent' => 1, 'widthPercent' => 10, 'heightPercent' => 10, 'z' => 0],
                ],
            ],
        ]);

        $canvas = Mockery::mock(CertificateCanvasRenderService::class);
        $canvas->shouldReceive('supportsTemplate')->once()->andReturn(false);
        $canvas->shouldReceive('renderPreviewPng')->never();

        $service = new TemplateThumbnailService($canvas, app(QrCodeService::class));
        $this->assertNull($service->generateFromCanvas($template, User::factory()->create()));
        $this->assertNull($template->fresh()->thumbnail_path);
    }

    public function test_publish_requests_a_thumbnail_quietly(): void
    {
        Storage::fake('public');

        $this->mock(TemplateThumbnailService::class, function ($mock) {
            $mock->shouldReceive('generateQuietly')->once()->andReturn('templates/1/thumbnail.png');
        });

        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['is_active' => false]);

        $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.publish', $template), [
                'canvas_json' => [
                    'elements' => [
                        ['id' => 'el_1', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJson(['status' => 'published']);
    }
}
