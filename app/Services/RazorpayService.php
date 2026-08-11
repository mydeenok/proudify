<?php

namespace App\Services;

use Razorpay\Api\Api;
use Razorpay\Api\Errors\SignatureVerificationError;

class RazorpayService
{
    private readonly Api $api;

    public function __construct()
    {
        $this->api = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    /**
     * Amount is always computed server-side from the plan's price — never
     * trusted from the client — and passed in paise/cents (Razorpay's
     * smallest-currency-unit convention).
     *
     * @return array{id: string, amount: int, currency: string}
     */
    public function createOrder(float $amount, string $currency): array
    {
        $order = $this->api->order->create([
            'receipt' => 'proudify_'.uniqid(),
            'amount' => (int) round($amount * 100),
            'currency' => $currency,
            'payment_capture' => 1,
        ]);

        return [
            'id' => $order->id,
            'amount' => $order->amount,
            'currency' => $order->currency,
        ];
    }

    /**
     * Amount is always computed server-side from the order's own
     * total_amount, in paise, matching createOrder()'s convention.
     *
     * @return array{id: string, status: string}
     */
    public function refund(string $paymentId, float $amount): array
    {
        $refund = $this->api->payment->fetch($paymentId)->refund([
            'amount' => (int) round($amount * 100),
        ]);

        return [
            'id' => $refund->id,
            'status' => $refund->status,
        ];
    }

    public function verifySignature(string $orderId, string $paymentId, string $signature): bool
    {
        try {
            $this->api->utility->verifyPaymentSignature([
                'razorpay_order_id' => $orderId,
                'razorpay_payment_id' => $paymentId,
                'razorpay_signature' => $signature,
            ]);

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }

    public function verifyWebhookSignature(string $payload, string $signature): bool
    {
        try {
            $this->api->utility->verifyWebhookSignature($payload, $signature, config('services.razorpay.webhook_secret'));

            return true;
        } catch (SignatureVerificationError) {
            return false;
        }
    }
}
