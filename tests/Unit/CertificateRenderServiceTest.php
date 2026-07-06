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
}
