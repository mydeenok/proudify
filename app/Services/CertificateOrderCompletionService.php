<?php

namespace App\Services;

use App\Actions\Certificates\IssueSingleCertificateAction;
use App\Models\CertificateOrder;
use App\Notifications\AdminCertificateOrderIssuanceFailedNotification;
use App\Support\NotifyAdmins;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place a paid CertificateOrder actually turns into issued
 * certificates. Both checkout controllers' verifyPayment() and the
 * Razorpay webhook call into this one method, so issuance-after-payment
 * logic exists exactly once regardless of which path confirms the payment
 * first (client-side handler vs. webhook safety net).
 */
class CertificateOrderCompletionService
{
    public function __construct(
        private readonly IssueSingleCertificateAction $issueAction,
        private readonly BulkUploadWizardService $wizardService,
    ) {}

    /**
     * @param  array{razorpay_order_id: string, razorpay_payment_id: string, razorpay_signature: ?string}  $paymentFields
     */
    public function complete(CertificateOrder $order, array $paymentFields): void
    {
        DB::transaction(function () use ($order, $paymentFields) {
            /** @var CertificateOrder $locked */
            $locked = CertificateOrder::whereKey($order->id)->lockForUpdate()->first();

            // Idempotency: the client-side verifyPayment call and the
            // webhook can both arrive for the same order (e.g. the browser
            // tab closes right after payment succeeds but before its own
            // request completes) - whichever gets here first wins, the
            // other is a safe no-op.
            if ($locked->status !== 'pending') {
                return;
            }

            $locked->update([
                'status' => 'paid',
                'razorpay_order_id' => $paymentFields['razorpay_order_id'],
                'razorpay_payment_id' => $paymentFields['razorpay_payment_id'],
                'razorpay_signature' => $paymentFields['razorpay_signature'] ?? null,
                'paid_at' => now(),
            ]);

            $order = $locked;
        });

        // Issuance happens outside the transaction above - it can involve
        // synchronous PDF/QR generation (single) or dispatching a queued
        // batch (bulk), neither of which should hold a row lock.
        if ($order->type === 'single') {
            $this->issueSingle($order);
        } else {
            $this->issueBulk($order);
        }
    }

    private function issueSingle(CertificateOrder $order): void
    {
        try {
            $certificate = $this->issueAction->execute($order->user, $order->template, $order->payload ?? []);
            $order->update(['certificate_id' => $certificate->id]);
        } catch (Throwable $exception) {
            // Money already moved and there's no refund API to reuse
            // (RazorpayService has none) - log loudly and let a human
            // resolve it rather than silently losing the payment.
            Log::critical('Certificate order paid but issuance failed.', [
                'certificate_order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);

            NotifyAdmins::notify(
                new AdminCertificateOrderIssuanceFailedNotification($order, $exception->getMessage()),
                'Failed to send admin certificate-order-issuance-failed alert.',
                ['certificate_order_id' => $order->id],
            );
        }
    }

    private function issueBulk(CertificateOrder $order): void
    {
        $order->loadMissing('batch');

        if (! $order->batch) {
            Log::critical('Certificate order paid but has no batch to issue.', ['certificate_order_id' => $order->id]);

            return;
        }

        try {
            $this->wizardService->confirm($order->batch);
        } catch (Throwable $exception) {
            Log::critical('Certificate order paid but bulk dispatch failed.', [
                'certificate_order_id' => $order->id,
                'exception' => $exception->getMessage(),
            ]);

            NotifyAdmins::notify(
                new AdminCertificateOrderIssuanceFailedNotification($order, $exception->getMessage()),
                'Failed to send admin certificate-order-issuance-failed alert.',
                ['certificate_order_id' => $order->id],
            );
        }
    }
}
