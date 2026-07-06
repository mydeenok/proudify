<?php

namespace Tests\Unit;

use App\Services\TemplateBackgroundImportService;
use Tests\TestCase;

class TemplateBackgroundImportServiceTest extends TestCase
{
    public function test_it_marks_and_fills_img_src_style_tokens(): void
    {
        $html = '<img src="{qrcode}" alt="QR Code" /><img src="{signature}" alt="Signature" />';

        $result = app(TemplateBackgroundImportService::class)->renderForDetection($html);

        $this->assertStringContainsString('data-bind="qrcode"', $result);
        $this->assertStringContainsString('data-bind="signature"', $result);
        $this->assertStringContainsString('src="data:image/', $result);
        $this->assertStringNotContainsString('{qrcode}', $result);
        $this->assertStringNotContainsString('{signature}', $result);
        $this->assertStringNotContainsString('src="<img', $result);
    }

    public function test_it_marks_and_fills_bare_block_tokens(): void
    {
        $html = '<div>{qrcode}</div>';

        $result = app(TemplateBackgroundImportService::class)->renderForDetection($html);

        $this->assertStringContainsString('data-bind="qrcode"', $result);
        $this->assertStringContainsString('src="data:image/', $result);
        $this->assertStringNotContainsString('{qrcode}', $result);
    }

    public function test_it_marks_and_fills_text_tokens(): void
    {
        $html = '<h1>{title}</h1><p>{recipient_name}</p>';

        $result = app(TemplateBackgroundImportService::class)->renderForDetection($html);

        $this->assertStringContainsString('<h1><span data-bind="title">Certificate Title</span></h1>', $result);
        $this->assertStringContainsString('data-bind="recipient_name"', $result);
        $this->assertStringNotContainsString('{title}', $result);
        $this->assertStringNotContainsString('{recipient_name}', $result);
    }

    public function test_blank_known_tokens_removes_img_src_style_and_text_tokens(): void
    {
        $html = '<h1>{title}</h1><img src="{qrcode}" alt="QR" /><div>{signature}</div>';

        $result = app(TemplateBackgroundImportService::class)->blankKnownTokens($html);

        $this->assertStringNotContainsString('{title}', $result);
        $this->assertStringNotContainsString('{qrcode}', $result);
        $this->assertStringNotContainsString('{signature}', $result);
        $this->assertStringNotContainsString('<img', $result);
        $this->assertStringContainsString('<h1></h1>', $result);
    }

    public function test_blank_known_tokens_preserves_unrelated_markup(): void
    {
        $html = '<div class="decorative-border"><h1>{title}</h1><p class="tagline">Award Winning</p></div>';

        $result = app(TemplateBackgroundImportService::class)->blankKnownTokens($html);

        $this->assertStringContainsString('class="decorative-border"', $result);
        $this->assertStringContainsString('Award Winning', $result);
    }
}
