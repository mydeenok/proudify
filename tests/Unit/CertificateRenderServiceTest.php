<?php

namespace Tests\Unit;

use App\Models\Certificate;
use App\Models\Template;
use App\Models\User;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateRenderServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_image_src_placeholders_are_replaced_with_data_uris(): void
    {
        Storage::fake('public');

        $qrPath = UploadedFile::fake()->image('qr.png')->store('certificates/qr', 'public');
        $signaturePath = UploadedFile::fake()->image('signature.png')->store('signatures', 'public');
        $logoPath = UploadedFile::fake()->image('logo.png')->store('organization-logos', 'public');

        $template = Template::factory()->create([
            'html_content' => <<<'HTML'
                <img src="{qrcode}" alt="QR Code" />
                <img src="{signature}" alt="Signature" />
                <img src="{company_logo_0}" alt="Company Logo" />
                HTML,
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'qr_code_path' => $qrPath,
            'signature_path' => $signaturePath,
            'company_logos' => [$logoPath],
        ]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringContainsString('src="data:image/', $html);
        $this->assertStringNotContainsString('src="{qrcode}"', $html);
        $this->assertStringNotContainsString('src="{signature}"', $html);
        $this->assertStringNotContainsString('src="{company_logo_0}"', $html);
        $this->assertStringNotContainsString('<img src="<img', $html);
    }

    public function test_block_placeholders_render_full_image_tags(): void
    {
        Storage::fake('public');

        $qrPath = UploadedFile::fake()->image('qr.png')->store('certificates/qr', 'public');

        $template = Template::factory()->create([
            'html_content' => '<div>{qrcode}</div>',
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'qr_code_path' => $qrPath,
        ]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringContainsString('<div><img src="data:image/', $html);
        $this->assertStringNotContainsString('{qrcode}', $html);
    }

    public function test_image_src_placeholders_support_single_quoted_attributes(): void
    {
        Storage::fake('public');

        $qrPath = UploadedFile::fake()->image('qr.png')->store('certificates/qr', 'public');

        $template = Template::factory()->create([
            'html_content' => '<img src=\'{qrcode}\' alt="QR Code" />',
        ]);

        $certificate = Certificate::factory()->create([
            'template_id' => $template->id,
            'qr_code_path' => $qrPath,
        ]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringContainsString('src=\'data:image/', $html);
        $this->assertStringNotContainsString('{qrcode}', $html);
    }

    public function test_every_certificate_carries_the_verification_watermark(): void
    {
        $template = Template::factory()->create(['html_content' => '<html><body><h1>{title}</h1></body></html>']);
        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringContainsString('Verified by proudify.in', $html);
    }

    public function test_the_watermark_is_appended_even_without_a_body_tag(): void
    {
        $template = Template::factory()->create(['html_content' => '<h1>{title}</h1>']);
        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        $this->assertStringContainsString('Verified by proudify.in', $html);
    }

    public function test_the_preview_also_carries_the_verification_watermark(): void
    {
        $template = Template::factory()->create(['html_content' => '<html><body><h1>{title}</h1></body></html>']);
        $issuer = User::factory()->create();

        $html = app(CertificateRenderService::class)->renderPreviewHtml($template, $issuer, []);

        $this->assertStringContainsString('Verified by proudify.in', $html);
    }

    public function test_the_watermark_has_no_hardcoded_font_so_it_inherits_the_templates_own(): void
    {
        $template = Template::factory()->create(['html_content' => '<html><body><h1>{title}</h1></body></html>']);
        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        $html = app(CertificateRenderService::class)->renderHtml($certificate);

        preg_match('/<div style="([^"]*)">Verified by proudify\.in<\/div>/', $html, $matches);

        $this->assertNotEmpty($matches, 'Watermark div not found.');
        $this->assertStringNotContainsString('font-family', $matches[1]);
        $this->assertStringContainsString('font-size:11px', $matches[1]);
    }

    public function test_watermark_corner_is_detected_once_and_cached_on_the_template(): void
    {
        // Variance (not darkness) is what the detector measures - a solid
        // color fill is perfectly uniform and would read as "empty" same
        // as blank white, so this needs real content (text has contrasting
        // edges) specifically in the top corners; the bottom stays blank.
        $template = Template::factory()->create([
            'watermark_corner' => null,
            'orientation' => 'landscape',
            'html_content' => <<<'HTML'
                <html><body style="margin:0;">
                    <div style="position:absolute;top:5px;left:5px;font-size:36px;font-weight:bold;">BUSY TOP LEFT CONTENT HERE XXXX</div>
                    <div style="position:absolute;top:5px;right:5px;font-size:36px;font-weight:bold;">BUSY TOP RIGHT CONTENT HERE XXXX</div>
                </body></html>
                HTML,
        ]);
        $certificate = Certificate::factory()->create(['template_id' => $template->id]);

        app(CertificateRenderService::class)->renderHtml($certificate);

        $template->refresh();
        $this->assertContains($template->watermark_corner, ['bottom-left', 'bottom-right']);

        // Second render must not re-trigger detection - it reads the now-
        // cached value. If this were slow (seconds, a real Chrome launch),
        // that alone would indicate the cache isn't being used.
        $start = microtime(true);
        app(CertificateRenderService::class)->renderHtml($certificate->fresh());
        $this->assertLessThan(1.0, microtime(true) - $start, 'Second render should not re-run corner detection.');
    }

}
