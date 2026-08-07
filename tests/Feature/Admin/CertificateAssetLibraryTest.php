<?php

namespace Tests\Feature\Admin;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CertificateAssetLibraryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admins_can_list_seeded_certificate_assets(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)
            ->getJson(route('admin.certificate-assets.index'));

        $response->assertOk();
        $this->assertNotEmpty($response->json());
        $this->assertTrue(collect($response->json())->contains(
            fn (array $asset) => str_contains($asset['path'], 'certificate-assets/')
        ));
    }

    public function test_admins_can_upload_a_shared_asset(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        $file = UploadedFile::fake()->image('seal.png', 80, 80);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.certificate-assets.store'), ['file' => $file]);

        $response->assertCreated()
            ->assertJsonStructure(['path', 'url', 'name']);

        Storage::disk('public')->assertExists($response->json('path'));
    }

    public function test_tenants_cannot_access_the_asset_library(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->getJson(route('admin.certificate-assets.index'))
            ->assertForbidden();
    }

    public function test_builder_sample_preview_endpoint_returns_preview(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'is_active' => true,
            'html_content' => '<h1>{title}</h1><p>{recipient_name}</p>',
            'canvas_json' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.templates.builder.preview-sample', $template))
            ->assertOk()
            ->assertHeader('X-Preview-Mode', 'html')
            ->assertSee('Sample Certificate Title', false)
            ->assertSee('Alex Recipient', false);
    }
}
