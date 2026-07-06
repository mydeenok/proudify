<?php

namespace Tests\Feature\Certificates;

use App\Models\Subscription;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PreviewCertificateTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_page_renders_the_real_template_with_sample_data(): void
    {
        Subscription::factory()->free()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'html_content' => '<h1>{title}</h1><p>{recipient_name}</p><img src="{qrcode}" alt="QR">',
        ]);

        $response = $this->actingAs($user)->post(route('certificates.preview'), [
            'template_id' => $template->id,
            'title' => 'My Custom Title',
            'recipient_name' => 'Jane Doe',
        ]);

        $response->assertOk();
        $response->assertSee('My Custom Title');
        $response->assertSee('Jane Doe');
    }

    public function test_preview_render_endpoint_returns_html_with_no_leaked_tokens(): void
    {
        Subscription::factory()->free()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'html_content' => '<h1>{title}</h1><p>{recipient_name}</p><img src="{qrcode}" alt="QR"><div>{signature}</div>',
        ]);

        $response = $this->actingAs($user)->postJson(route('certificates.preview.render'), [
            'template_id' => $template->id,
            'title' => 'Preview Title',
            'recipient_name' => 'Preview Recipient',
        ]);

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringContainsString('Preview Title', $html);
        $this->assertStringContainsString('Preview Recipient', $html);
        $this->assertStringContainsString('src="data:image/', $html);
        $this->assertStringNotContainsString('{title}', $html);
        $this->assertStringNotContainsString('{recipient_name}', $html);
        $this->assertStringNotContainsString('{qrcode}', $html);
        $this->assertStringNotContainsString('src="<img', $html);
    }

    public function test_preview_uses_the_issuers_real_signature_and_logo(): void
    {
        Storage::fake('public');
        Subscription::factory()->free()->create();

        $signaturePath = UploadedFile::fake()->image('sig.png')->store('signatures', 'public');
        $logoPath = UploadedFile::fake()->image('logo.png')->store('organization-logos', 'public');

        $user = User::factory()->create([
            'signature_path' => $signaturePath,
            'org_logos' => [$logoPath],
        ]);

        $template = Template::factory()->create([
            'html_content' => '<img src="{signature}" alt="Sig"><img src="{company_logo}" alt="Logo">',
        ]);

        $response = $this->actingAs($user)->postJson(route('certificates.preview.render'), [
            'template_id' => $template->id,
        ]);

        $response->assertOk();
        $html = $response->getContent();

        $this->assertStringNotContainsString('src="{signature}"', $html);
        $this->assertStringNotContainsString('src="{company_logo}"', $html);
        $this->assertSame(2, substr_count($html, 'src="data:image/'));
    }

    public function test_preview_falls_back_to_placeholder_defaults_when_fields_are_blank(): void
    {
        Subscription::factory()->free()->create();
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'html_content' => '<h1>{title}</h1><p>{recipient_name}</p>',
        ]);

        $response = $this->actingAs($user)->postJson(route('certificates.preview.render'), [
            'template_id' => $template->id,
        ]);

        $response->assertOk();
        $response->assertSee('Certificate Title');
        $response->assertSee('Recipient Name');
    }

    public function test_preview_requires_an_active_template(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create(['is_active' => false]);

        $this->actingAs($user)->postJson(route('certificates.preview.render'), [
            'template_id' => $template->id,
        ])->assertNotFound();
    }

    public function test_guests_cannot_access_the_preview_endpoints(): void
    {
        $template = Template::factory()->create();

        $this->post(route('certificates.preview'), ['template_id' => $template->id])
            ->assertRedirect(route('login'));
    }
}
