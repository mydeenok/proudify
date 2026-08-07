<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MigrateTemplatesToCanvasCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scaffolds_canvas_json_for_legacy_html_templates(): void
    {
        $admin = User::factory()->admin()->create();
        $legacy = Template::factory()->create([
            'name' => 'Legacy HTML',
            'html_content' => '<h1>{title}</h1>',
            'canvas_json' => null,
            'is_active' => true,
            'created_by' => $admin->id,
        ]);

        $this->artisan('templates:migrate-to-canvas')
            ->assertSuccessful();

        $legacy->refresh();
        $this->assertIsArray($legacy->canvas_json);
        $this->assertNotEmpty($legacy->canvas_json['elements']);
        $this->assertArrayNotHasKey('background_html', $legacy->canvas_json);
        $this->assertFalse($legacy->is_active, 'Scaffolded templates stay draft until an admin publishes.');
        $this->assertTrue(
            collect($legacy->canvas_json['elements'])->contains(fn ($el) => ($el['type'] ?? null) === 'shape'),
            'Scaffold includes a decorative shape border.'
        );
    }

    public function test_dry_run_does_not_write(): void
    {
        $legacy = Template::factory()->create(['canvas_json' => null]);

        $this->artisan('templates:migrate-to-canvas', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertNull($legacy->refresh()->canvas_json);
    }
}
