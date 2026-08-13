<?php

namespace App\Http\Controllers;

use App\Models\CertificateOrder;
use App\Services\CertificateOrderCompletionService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Razorpay\Api\Errors\Error as RazorpayError;

class BulkUploadCheckoutController extends Controller
{
    public function show(Request $request, CertificateOrder $certificateOrder): View
    {
        $this->authorizeAccess($request, $certificateOrder);
        abort_unless($certificateOrder->type === 'bulk', 404);
        abort_unless($certificateOrder->isPayable(), 410, 'This checkout link has expired.');

        return view('bulk-upload.checkout', ['order' => $certificateOrder->loadMissing('template', 'batch')]);
    }

    public function createOrder(Request $request, CertificateOrder $certificateOrder, RazorpayService $razorpay): JsonResponse
    {
        $this->authorizeAccess($request, $certificateOrder);

        if (! $certificateOrder->isPayable()) {
            return response()->json(['message' => 'This checkout link has expired.'], 410);
        }

        try {
            $order = $razorpay->createOrder((float) $certificateOrder->total_amount, $certificateOrder->currency);
        } catch (RazorpayError $exception) {
            Log::error('Razorpay order creation failed for a bulk certificate order.', [
                'certificate_order_id' => $certificateOrder->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to start checkout right now. Please try again shortly.'], 502);
        }

        // See CertificateCheckoutController::createOrder() - persisted so
        // the webhook safety net can find this order by razorpay_order_id
        // while it's still pending.
        $certificateOrder->update(['razorpay_order_id' => $order['id']]);

        return response()->json([
            ...$order,
            'key' => config('services.razorpay.key'),
        ]);
    }

    public function verifyPayment(Request $request, CertificateOrder $certificateOrder, RazorpayService $razorpay, CertificateOrderCompletionService $completion): JsonResponse
    {
        $this->authorizeAccess($request, $certificateOrder);

        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
        ]);

        if ($certificateOrder->status === 'paid') {
            return response()->json(['success' => true, 'redirect' => route('bulk-upload.status', $certificateOrder->certificate_batch_id)]);
        }

        $signatureValid = $razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        );

        if (! $signatureValid) {
            return response()->json(['message' => 'Payment verification failed.'], 422);
        }

        $completion->complete($certificateOrder, $validated);

        return response()->json(['success' => true, 'redirect' => route('bulk-upload.status', $certificateOrder->certificate_batch_id)]);
    }

    private function authorizeAccess(Request $request, CertificateOrder $certificateOrder): void
    {
        abort_unless($certificateOrder->user_id === $request->user()->id, 403);
    }
}
