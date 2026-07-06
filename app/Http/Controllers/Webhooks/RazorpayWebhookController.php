<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Services\RazorpayService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * New vs. the reference app: without this, a payment that Razorpay later
 * reports as failed (e.g. a delayed bank decline) leaves the
 * UserSubscription stuck at payment_status=pending forever, since
 * verifyPayment() is the only other place that ever wrote to that column.
 */
class RazorpayWebhookController extends Controller
{
    public function handle(Request $request, RazorpayService $razorpay): Response
    {
        $signature = $request->header('X-Razorpay-Signature', '');

        if (! $razorpay->verifyWebhookSignature($request->getContent(), $signature)) {
            return response('Invalid signature', 400);
        }

        $event = $request->input('event');
        $paymentId = $request->input('payload.payment.entity.id');

        if (! $paymentId) {
            return response('OK');
        }

        $subscription = UserSubscription::where('razorpay_payment_id', $paymentId)->first();

        if (! $subscription) {
            return response('OK');
        }

        match ($event) {
            'payment.failed' => $subscription->update(['payment_status' => 'failed', 'is_active' => false]),
            'payment.captured', 'order.paid' => $subscription->update(['payment_status' => 'completed']),
            default => null,
        };

        return response('OK');
    }
}
