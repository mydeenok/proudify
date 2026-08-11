<?php

namespace App\Notifications;

use App\Models\CertificateOrder;
use Illuminate\Notifications\Notification;

/**
 * Payment succeeded but the certificate itself failed to issue afterward -
 * money already moved and there's no refund API to reuse (RazorpayService
 * has none), so this needs a human to resolve manually.
 */
class AdminCertificateOrderIssuanceFailedNotification extends Notification
{
    public function __construct(public CertificateOrder $order, public string $errorMessage) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $user = $this->order->user;

        return [
            'title' => 'Certificate order paid but issuance failed',
            'body' => 'Payment for '.($user?->organization_name ?? $user?->name ?? 'a tenant')
                ." was captured but certificate generation failed: {$this->errorMessage}",
            'route' => 'admin.certificates.index',
            'route_params' => [],
        ];
    }
}
