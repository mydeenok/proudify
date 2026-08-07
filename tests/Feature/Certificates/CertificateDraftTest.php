<?php

namespace Tests\Feature\Certificates;

use App\Models\CertificateDraft;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateDraftTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_and_restore_a_draft_for_a_template(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $template = Template::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->postJson(route('certificates.drafts.save'), [
                'template_id' => $template->id,
                'title' => 'Draft Title',
                'recipient_name' => 'Draft Person',
                'recipient_email' => 'draft@example.com',
                'description' => 'Draft body',
                'date_of_issue' => '2026-08-07',
            ])
            ->assertOk()
            ->assertJson(['status' => 'saved']);

        $this->assertDatabaseHas('certificate_drafts', [
            'user_id' => $user->id,
            'template_id' => $template->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('certificates.create', ['template' => $template->id]));

        $response->assertOk()
            ->assertSee('Draft Title', false)
            ->assertSee('Draft Person', false)
            ->assertSee('design comes from the admin template', false);
    }

    public function test_draft_is_owner_scoped_and_can_be_deleted(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $template = Template::factory()->create(['is_active' => true]);

        CertificateDraft::query()->create([
            'user_id' => $owner->id,
            'template_id' => $template->id,
            'payload' => ['title' => 'Secret'],
        ]);

        $this->actingAs($other)
            ->deleteJson(route('certificates.drafts.destroy'), ['template_id' => $template->id])
            ->assertOk();

        $this->assertDatabaseHas('certificate_drafts', [
            'user_id' => $owner->id,
            'template_id' => $template->id,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('certificates.drafts.destroy'), ['template_id' => $template->id])
            ->assertOk()
            ->assertJson(['status' => 'deleted']);

        $this->assertDatabaseMissing('certificate_drafts', [
            'user_id' => $owner->id,
            'template_id' => $template->id,
        ]);
    }

    public function test_issue_another_clears_recipient_fields_in_create_form(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $template = Template::factory()->create(['is_active' => true]);

        CertificateDraft::query()->create([
            'user_id' => $user->id,
            'template_id' => $template->id,
            'payload' => [
                'title' => 'Keep Title',
                'recipient_name' => 'Old Recipient',
                'recipient_email' => 'old@example.com',
            ],
        ]);

        $response = $this->actingAs($user)
            ->get(route('certificates.create', [
                'template' => $template->id,
                'issue_another' => 1,
            ]));

        $response->assertOk()
            ->assertSee('Keep Title', false)
            ->assertDontSee('Old Recipient', false)
            ->assertDontSee('old@example.com', false);
    }
}
