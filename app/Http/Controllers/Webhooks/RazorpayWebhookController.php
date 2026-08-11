<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\CertificateOrder;
use App\Models\UserSubscription;
use App\Notifications\AdminPaymentFailedNotification;
use App\Notifications\PaymentFailedNotification;
use App\Services\CertificateOrderCompletionService;
use App\Services\RazorpayService;
use App\Support\NotifyAdmins;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * New vs. the reference app: without this, a payment that Razorpay later
 * reports as failed (e.g. a delayed bank decline) leaves the
 * UserSubscription/CertificateOrder stuck at pending forever, since
 * verifyPayment() on either checkout controller is the only other place
 * that ever writes to that status.
 *
 * Also acts as the safety net for CertificateOrder completion if the
 * browser tab closes right after payment succeeds but before the
 * client-side verifyPayment() call completes - see
 * CertificateOrderCompletionService, which both this webhook and the
 * checkout controllers' verifyPayment() call into.
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayService $razorpay, CertificateOrderCompletionService $completion): Response
    {
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $razorpay->verifyWebhookSignature($request->getContent(), $signature)) {
            return response('Invalid signature', 400);
        }

        $event = $request->input('event');
        $paymentId = $request->input('payload.payment.entity.id');
        $orderId = $request->input('payload.payment.entity.order_id');

        if (! $paymentId) {
            return response('OK');
        }

        $subscription = UserSubscription::where('razorpay_payment_id', $paymentId)->first();

        if ($subscription) {
            match ($event) {
                'payment.failed' => $this->handlePaymentFailed($subscription),
                'payment.captured', 'order.paid' => $subscription->update(['payment_status' => 'completed']),
                default => null,
            };

            return response('OK');
        }

        $certificateOrder = CertificateOrder::where('razorpay_payment_id', $paymentId)
            ->orWhere('razorpay_order_id', $orderId)
            ->first();

        if ($certificateOrder) {
            match ($event) {
                'payment.failed' => $certificateOrder->update(['status' => 'failed']),
                'payment.captured', 'order.paid' => $completion->complete($certificateOrder, [
                    'razorpay_order_id' => $orderId ?? $certificateOrder->razorpay_order_id,
                    'razorpay_payment_id' => $paymentId,
                    'razorpay_signature' => null,
                ]),
                default => null,
            };
        }

        return response('OK');
    }

    private function handlePaymentFailed(UserSubscription $subscription): void
    {
        $subscription->update(['payment_status' => 'failed', 'is_active' => false]);
        $subscription->loadMissing('user', 'subscription');

        try {
            $subscription->user->notify(new PaymentFailedNotification($subscription));
        } catch (Throwable $exception) {
            Log::error('Failed to send payment-failed email.', ['user_subscription_id' => $subscription->id, 'exception' => $exception->getMessage()]);
        }

        NotifyAdmins::notify(
            new AdminPaymentFailedNotification($subscription),
            'Failed to send admin payment-failed alert.',
            ['user_subscription_id' => $subscription->id],
        );
    }
}
