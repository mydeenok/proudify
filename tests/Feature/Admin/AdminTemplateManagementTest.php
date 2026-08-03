<?php

namespace Tests\Feature\Admin;

use App\Livewire\Admin\TemplatesTable;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTemplateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_the_template_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.templates.index'))
            ->assertForbidden();
    }

    public function test_search_filters_templates_by_name(): void
    {
        $admin = User::factory()->admin()->create();
        Template::factory()->create(['name' => 'Achievement Award']);
        Template::factory()->create(['name' => 'Completion Certificate']);

        $this->actingAs($admin)
            ->get(route('admin.templates.index', ['search' => 'Achievement']))
            ->assertOk()
            ->assertSee('Achievement Award')
            ->assertDontSee('Completion Certificate');
    }

    public function test_search_filters_templates_reactively(): void
    {
        $admin = User::factory()->admin()->create();
        Template::factory()->create(['name' => 'Achievement Award']);
        Template::factory()->create(['name' => 'Completion Certificate']);

        Livewire::actingAs($admin)
            ->test(TemplatesTable::class)
            ->set('search', 'Achievement')
            ->assertSee('Achievement Award')
            ->assertDontSee('Completion Certificate');
    }

    public function test_an_admin_can_toggle_template_status(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['is_active' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.templates.toggle-status', $template))
            ->assertRedirect();

        $this->assertFalse($template->fresh()->is_active);
    }

    public function test_an_admin_can_delete_a_template(): void
    {
        $admin = User::factory()->admin()->create();
        $template = Template::factory()->create(['name' => 'Doomed Template']);

        $this->actingAs($admin)
            ->delete(route('admin.templates.destroy', $template))
            ->assertRedirect();

        $this->assertSoftDeleted($template);
    }

    public function test_non_admins_cannot_render_the_templates_table(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(TemplatesTable::class)
            ->assertForbidden();
    }
}
