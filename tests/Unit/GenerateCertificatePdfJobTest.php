<?php

namespace Tests\Unit;

use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Services\CertificateRenderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class GenerateCertificatePdfJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_pdf_job_refreshes_the_certificate_before_rendering(): void
    {
        Storage::fake('public');

        $qrPath = UploadedFile::fake()->image('qr.png')->store('certificates/qr', 'public');

        $certificate = Certificate::factory()->create([
            'qr_code_path' => $qrPath,
        ]);

        $staleCertificate = Certificate::findOrFail($certificate->id);
        $staleCertificate->qr_code_path = null;

        $renderService = Mockery::mock(CertificateRenderService::class);
        $renderService->shouldReceive('renderPdf')
            ->once()
            ->with(Mockery::on(fn (Certificate $model) => $model->qr_code_path === $qrPath))
            ->andReturn("certificates/{$certificate->user_id}/{$certificate->uuid}.pdf");

        (new GenerateCertificatePdfJob($staleCertificate))->handle($renderService);

        $certificate->refresh();
        $this->assertSame("certificates/{$certificate->user_id}/{$certificate->uuid}.pdf", $certificate->pdf_path);
    }
}
