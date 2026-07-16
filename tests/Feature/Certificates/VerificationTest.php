<?php

namespace Tests\Feature\Certificates;

use App\Models\Certificate;
use App\Models\CertificateVerification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_valid_certificate_verifies_successfully_and_is_logged(): void
    {
        $certificate = Certificate::factory()->create();

        $response = $this->get($certificate->verify_url);

        $response->assertOk();
        $response->assertViewHas('status', 'valid');
        $this->assertDatabaseHas('certificate_verifications', [
            'certificate_id' => $certificate->id,
            'result' => 'valid',
        ]);
    }

    public function test_a_revoked_certificate_reports_revoked(): void
    {
        $certificate = Certificate::factory()->revoked()->create();

        $this->get($certificate->verify_url)
            ->assertViewHas('status', 'revoked');
    }

    public function test_an_expired_certificate_reports_expired(): void
    {
        $certificate = Certificate::factory()->expired()->create();

        $this->get($certificate->verify_url)
            ->assertViewHas('status', 'expired');
    }

    public function test_a_wrong_verification_code_reports_not_found_without_leaking_existence(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get(route('certificates.verify', ['uuid' => $certificate->uuid, 'code' => 'WRONGCOD']))
            ->assertViewHas('status', 'not_found')
            ->assertViewHas('certificate', null);
    }

    public function test_a_tampered_signature_is_rejected_even_with_matching_uuid_and_code(): void
    {
        $certificate = Certificate::factory()->create();

        // Simulate a row edited directly in the database, bypassing the app.
        $certificate->forceFill(['verification_signature' => 'forged-signature'])->save();

        $this->get($certificate->verify_url)
            ->assertViewHas('status', 'not_found');
    }

    public function test_an_unknown_uuid_reports_not_found_and_is_still_logged(): void
    {
        $response = $this->get(route('certificates.verify', ['uuid' => (string) \Illuminate\Support\Str::uuid(), 'code' => 'AAAAAAAA']));

        $response->assertViewHas('status', 'not_found');
        $this->assertDatabaseHas('certificate_verifications', [
            'certificate_id' => null,
            'result' => 'not_found',
        ]);
    }

    public function test_a_valid_certificate_can_be_downloaded_publicly(): void
    {
        Storage::fake('local');

        $certificate = Certificate::factory()->create();
        $pdfPath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf";
        Storage::disk('local')->put($pdfPath, 'fake-pdf-contents');
        $certificate->forceFill(['pdf_path' => $pdfPath])->save();

        $response = $this->get(route('certificates.verify.download', [
            'uuid' => $certificate->uuid,
            'code' => $certificate->verification_code,
        ]));

        $response->assertOk();
        $response->assertHeader('content-disposition');
    }

    public function test_a_revoked_certificate_cannot_be_downloaded(): void
    {
        Storage::fake('local');

        $certificate = Certificate::factory()->revoked()->create();
        $pdfPath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf";
        Storage::disk('local')->put($pdfPath, 'fake-pdf-contents');
        $certificate->forceFill(['pdf_path' => $pdfPath])->save();

        $this->get(route('certificates.verify.download', [
            'uuid' => $certificate->uuid,
            'code' => $certificate->verification_code,
        ]))->assertNotFound();
    }

    public function test_a_valid_certificate_without_a_generated_pdf_cannot_be_downloaded_yet(): void
    {
        $certificate = Certificate::factory()->create();

        $this->get(route('certificates.verify.download', [
            'uuid' => $certificate->uuid,
            'code' => $certificate->verification_code,
        ]))->assertNotFound();
    }

    public function test_a_tampered_signature_cannot_be_downloaded(): void
    {
        $certificate = Certificate::factory()->create();
        $certificate->forceFill(['verification_signature' => 'forged-signature'])->save();

        $this->get(route('certificates.verify.download', [
            'uuid' => $certificate->uuid,
            'code' => $certificate->verification_code,
        ]))->assertNotFound();
    }
}
