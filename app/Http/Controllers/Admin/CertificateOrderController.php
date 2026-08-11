<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateOrder;
use App\Services\RazorpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Razorpay\Api\Errors\Error as RazorpayError;

class CertificateOrderController extends Controller
{
    public function index(): View
    {
        return view('admin.certificate-orders.index');
    }

    /**
     * Manual, admin-triggered only — there is no auto-refund path. A paid
     * order whose certificate never got issued needs a human to look at
     * why before money moves a second time; this button is that decision
     * point, not an automatic response to failure.
     */
    public function refund(CertificateOrder $certificateOrder, RazorpayService $razorpay): RedirectResponse
    {
        abort_unless($certificateOrder->status === 'paid', 422);
        abort_unless($certificateOrder->razorpay_payment_id, 422);

        try {
            $refund = $razorpay->refund((string) $certificateOrder->razorpay_payment_id, (float) $certificateOrder->total_amount);
        } catch (RazorpayError $exception) {
            Log::error('Razorpay refund failed for a certificate order.', [
                'certificate_order_id' => $certificateOrder->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->with('status', 'Refund failed: '.$exception->getMessage());
        }

        $certificateOrder->update([
            'status' => 'refunded',
            'razorpay_refund_id' => $refund['id'],
            'refunded_at' => now(),
            'refunded_by' => request()->user()->id,
        ]);

        return back()->with('status', 'Refund issued.');
    }
}
