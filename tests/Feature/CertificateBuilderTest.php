<?php

namespace Tests\Feature;

use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateBuilderTest extends TestCase
{
    use RefreshDatabase;

    private const CANVAS_JSON = [
        'elements' => [
            ['id' => 'el_1', 'type' => 'text', 'binding' => 'recipient_name', 'xPercent' => 10, 'yPercent' => 20, 'widthPercent' => 40, 'heightPercent' => 10, 'rotation' => 0, 'z' => 0],
        ],
    ];

    public function test_only_admins_can_open_the_builder(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.templates.builder', $template))
            ->assertForbidden();
    }

    public function test_saving_a_draft_does_not_touch_html_content(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['html_content' => '<p>original</p>', 'is_active' => true]);

        $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.save', $template), ['canvas_json' => self::CANVAS_JSON])
            ->assertOk()
            ->assertJson(['status' => 'saved']);

        $template->refresh();
        $this->assertSame('<p>original</p>', $template->html_content);
        $this->assertNotNull($template->canvas_json);
        $this->assertTrue($template->is_active, 'Draft save must not change the live/active state.');
    }

    public function test_publishing_renders_canvas_json_into_html_content_and_activates_the_template(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['html_content' => '<p>original</p>', 'is_active' => false]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.publish', $template), ['canvas_json' => self::CANVAS_JSON]);

        $response->assertOk()->assertJson(['status' => 'published']);

        $template->refresh();
        $this->assertStringContainsString('{recipient_name}', $template->html_content);
        $this->assertTrue($template->is_active);
    }

    public function test_a_published_templates_certificates_still_render_through_the_unchanged_m2_pipeline(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create();

        $this->actingAs($admin)->postJson(route('admin.templates.builder.publish', $template), [
            'canvas_json' => self::CANVAS_JSON,
        ]);

        $template->refresh();

        $renderService = app(\App\Services\CertificateRenderService::class);
        $certificate = \App\Models\Certificate::factory()->create([
            'template_id' => $template->id,
            'recipient_name' => 'Priya Sharma',
        ]);

        $html = $renderService->renderHtml($certificate);

        $this->assertStringContainsString('Priya Sharma', $html);
        $this->assertStringNotContainsString('{recipient_name}', $html);
    }

    public function test_first_save_imports_the_hand_written_html_as_a_background_with_known_tokens_blanked(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create([
            'html_content' => '<div class="border"><h1>{title}</h1><p>{recipient_name}</p></div>',
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.save', $template), ['canvas_json' => self::CANVAS_JSON]);

        $template->refresh();
        $background = $template->canvas_json['background_html'];

        $this->assertStringContainsString('class="border"', $background);
        $this->assertStringNotContainsString('{title}', $background);
        $this->assertStringNotContainsString('{recipient_name}', $background);
    }

    public function test_background_html_is_carried_forward_unchanged_on_later_saves(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['html_content' => '<h1>{title}</h1>']);

        $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.save', $template), ['canvas_json' => self::CANVAS_JSON]);
        $firstBackground = $template->refresh()->canvas_json['background_html'];

        // Even if html_content changes afterwards, the stored background
        // must not be silently recomputed from it on a later save.
        $template->update(['html_content' => '<h1>Completely different {title}</h1>']);

        $this->actingAs($admin)
            ->postJson(route('admin.templates.builder.save', $template), ['canvas_json' => self::CANVAS_JSON]);

        $this->assertSame($firstBackground, $template->refresh()->canvas_json['background_html']);
    }
}
