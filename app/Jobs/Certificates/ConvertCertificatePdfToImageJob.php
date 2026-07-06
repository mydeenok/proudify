<?php

namespace App\Jobs\Certificates;

use App\Jobs\Certificates\Concerns\RefreshesCertificateRecord;
use App\Models\Certificate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Imagick;
use Throwable;

/**
 * PDF -> JPG preview conversion. Ported from the reference app's
 * Imagick-primary / CLI-fallback pattern, but fixes Bug 4: a failed
 * conversion now persists image_generation_status = 'failed' instead of
 * failing silently, so the UI can surface a manual retry action.
 */
class ConvertCertificatePdfToImageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, RefreshesCertificateRecord, SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(public Certificate $certificate)
    {
        $this->onQueue(config('certificates.queues.certificates'));
        $this->tries = config('certificates.job_retries');
        $this->timeout = config('certificates.job_timeout');
    }

    /**
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping($this->certificate->id),
            (new RateLimited('pdf-conversions'))->dontRelease(),
        ];
    }

    public function handle(): void
    {
        $this->certificate->forceFill(['image_generation_status' => 'processing'])->save();

        $pdfAbsolutePath = Storage::disk('public')->path($this->certificate->pdf_path);
        $imageRelativePath = preg_replace('/\.pdf$/', '.jpg', $this->certificate->pdf_path);
        $imageAbsolutePath = Storage::disk('public')->path($imageRelativePath);

        $density = (int) config('certificates.image_density');
        $quality = (int) config('certificates.image_quality');

        extension_loaded('imagick')
            ? $this->convertWithImagick($pdfAbsolutePath, $imageAbsolutePath, $density, $quality)
            : $this->convertWithCli($pdfAbsolutePath, $imageAbsolutePath, $density, $quality);

        $this->certificate->forceFill([
            'image_path' => $imageRelativePath,
            'image_generation_status' => 'completed',
        ])->save();
    }

    public function failed(Throwable $exception): void
    {
        $this->freshCertificate()->forceFill(['image_generation_status' => 'failed'])->save();

        Log::error('Certificate PDF-to-image conversion failed permanently.', [
            'certificate_id' => $this->certificate->id,
            'exception' => $exception->getMessage(),
        ]);
    }

    private function convertWithImagick(string $pdfPath, string $imagePath, int $density, int $quality): void
    {
        $imagick = new Imagick;
        $imagick->setResolution($density, $density);
        $imagick->readImage("{$pdfPath}[0]");
        $imagick->setImageFormat('jpg');
        $imagick->setImageCompressionQuality($quality);
        $imagick->setImageBackgroundColor('white');
        $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $imagick->writeImage($imagePath);
        $imagick->clear();
        $imagick->destroy();
    }

    private function convertWithCli(string $pdfPath, string $imagePath, int $density, int $quality): void
    {
        foreach (['magick', 'convert'] as $binary) {
            $result = Process::run([$binary, '-density', (string) $density, "{$pdfPath}[0]", '-quality', (string) $quality, $imagePath]);

            if ($result->successful()) {
                return;
            }
        }

        throw new \RuntimeException('No working PDF-to-image conversion method available (Imagick and CLI fallbacks both failed).');
    }
}
