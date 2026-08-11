<?php

namespace App\Jobs\Billing;

use App\Models\CertificateOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

/**
 * A checkout tab left open (or abandoned) leaves the order stuck at
 * "pending" forever otherwise - this is the only thing that ever moves it
 * out of that state besides a completed payment.
 */
class ExpireStaleCertificateOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        CertificateOrder::query()
            ->pending()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->each(function (CertificateOrder $order) {
                $order->update(['status' => 'expired']);

                // Bulk-type orders' uploaded file lives on the batch's own
                // temp_upload_path with its own lifecycle - nothing to
                // clean up here beyond the order row itself.
                if ($order->type === 'single') {
                    $this->cleanUpUploadedImages($order);
                }
            });
    }

    private function cleanUpUploadedImages(CertificateOrder $order): void
    {
        $customImageFields = $order->payload['custom_image_fields'] ?? [];

        foreach ($customImageFields as $path) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
