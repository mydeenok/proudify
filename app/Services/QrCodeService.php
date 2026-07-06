<?php

namespace App\Services;

use App\Models\Certificate;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    /**
     * Generates a QR PNG encoding the certificate's public, signed verify
     * URL and returns the storage-relative path.
     */
    public function generate(Certificate $certificate): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $certificate->verify_url,
            encoding: new Encoding('UTF-8'),
            size: 300,
            margin: 10,
        ))->build();

        $relativePath = "certificates/{$certificate->user_id}/qr/{$certificate->uuid}.png";

        Storage::disk('public')->makeDirectory(dirname($relativePath));
        Storage::disk('public')->put($relativePath, $result->getString());

        return $relativePath;
    }

    /**
     * Builds a QR PNG for arbitrary data and returns it as a base64 data
     * URI rather than persisting it — used for the pre-issuance preview
     * page, where no real certificate (and therefore no verify URL) exists
     * yet to write a file against.
     */
    public function generateDataUri(string $data): string
    {
        $result = (new Builder(
            writer: new PngWriter,
            data: $data,
            encoding: new Encoding('UTF-8'),
            size: 300,
            margin: 10,
        ))->build();

        return 'data:image/png;base64,'.base64_encode($result->getString());
    }
}
