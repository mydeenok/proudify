<?php

namespace Tests\Unit;

use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Tests\TestCase;

class ConvertCertificatePdfToImageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_converts_the_pdf_and_marks_the_certificate_completed(): void
    {
        Storage::fake('public');

        $certificate = Certificate::factory()->create();

        $pdfRelativePath = "certificates/{$certificate->user_id}/{$certificate->uuid}.pdf";
        $pdfAbsolutePath = Storage::disk('public')->path($pdfRelativePath);
        Storage::disk('public')->makeDirectory(dirname($pdfRelativePath));

        $imagick = new Imagick;
        $imagick->newImage(200, 100, 'white');
        $imagick->setImageFormat('pdf');
        $imagick->writeImage($pdfAbsolutePath);
        $imagick->clear();
        $imagick->destroy();

        $certificate->forceFill(['pdf_path' => $pdfRelativePath])->save();

        (new ConvertCertificatePdfToImageJob($certificate))->handle();

        $certificate->refresh();
        $this->assertSame('completed', $certificate->image_generation_status);
        $this->assertNotNull($certificate->image_path);
        Storage::disk('public')->assertExists($certificate->image_path);
    }
}
