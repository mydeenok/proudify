<?php

namespace Tests\Feature\Admin;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCertificateManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admins_can_view_the_certificate_management_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.certificates.index'))
            ->assertForbidden();
    }

    public function test_search_filters_certificates_by_recipient(): void
    {
        $admin = User::factory()->admin()->create();
        Certificate::factory()->create(['recipient_name' => 'Alice Wonderland']);
        Certificate::factory()->create(['recipient_name' => 'Bob Builder']);

        $response = $this->actingAs($admin)->get(route('admin.certificates.index', ['search' => 'Alice']));

        $response->assertOk();
        $response->assertSee('Alice Wonderland');
        $response->assertDontSee('Bob Builder');
    }

    public function test_an_admin_can_revoke_an_active_certificate(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();

        $this->actingAs($admin)
            ->patch(route('admin.certificates.revoke', $certificate), ['reason' => 'Issued in error'])
            ->assertRedirect();

        $certificate->refresh();
        $this->assertSame('revoked', $certificate->status);
        $this->assertSame('Issued in error', $certificate->revoked_reason);
        $this->assertSame($admin->id, $certificate->revoked_by);
        $this->assertNotNull($certificate->revoked_at);
    }

    public function test_revoking_an_already_revoked_certificate_is_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->revoked()->create();

        $this->actingAs($admin)
            ->patch(route('admin.certificates.revoke', $certificate), ['reason' => 'Again'])
            ->assertStatus(422);
    }

    public function test_bulk_download_streams_a_zip_of_selected_certificates(): void
    {
        Storage::fake('local');

        $admin = User::factory()->admin()->create();
        $certificate = Certificate::factory()->create();

        Storage::disk('local')->put($pdfPath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf", 'fake-pdf-contents');
        $certificate->forceFill(['pdf_path' => $pdfPath])->save();

        $response = $this->actingAs($admin)->post(route('admin.certificates.bulk-download'), [
            'certificate_ids' => [$certificate->id],
        ]);

        $response->assertOk();
        $this->assertSame('application/zip', $response->headers->get('content-type'));
    }

    public function test_bulk_download_requires_at_least_one_certificate(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.certificates.bulk-download'), ['certificate_ids' => []])
            ->assertSessionHasErrors('certificate_ids');
    }
}
