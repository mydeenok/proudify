<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateOrder;
use App\Services\RazorpayService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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
        abort_unless($certificateOrder->razorpay_payment_id, 422);

        // Atomically claim the order before calling Razorpay - without
        // this, two near-simultaneous requests (double-click, a retried
        // request) could both pass a plain status==='paid' check before
        // either write landed, and both fire a real refund call for the
        // same order. lockForUpdate() serializes concurrent admins hitting
        // this route for the same order; only the first to acquire the
        // lock sees status='paid' and proceeds - the provisional
        // 'refunded' write closes the race window before the (slow,
        // external) Razorpay call even starts.
        $claimed = DB::transaction(function () use ($certificateOrder) {
            $locked = CertificateOrder::whereKey($certificateOrder->id)->lockForUpdate()->first();

            if ($locked->status !== 'paid') {
                return false;
            }

            $locked->update(['status' => 'refunded']);

            return true;
        });

        if (! $claimed) {
            return back()->with('status', 'This order is not eligible for a refund (already refunded, or not paid).');
        }

        // $certificateOrder's in-memory status is still whatever it was
        // when the route resolved it (before the transaction above ran on
        // a separate $locked instance) - refresh() so the catch block's
        // update(['status' => 'paid']) is a real change Eloquent's dirty-
        // tracking picks up, not a same-value no-op that leaves the DB row
        // stuck at 'refunded' with no actual refund having happened.
        $certificateOrder->refresh();

        try {
            $refund = $razorpay->refund((string) $certificateOrder->razorpay_payment_id, (float) $certificateOrder->total_amount);
        } catch (RazorpayError $exception) {
            // Refund call itself failed - release the claim so a retry is
            // possible instead of leaving the order stuck at 'refunded'
            // with no actual refund having happened.
            $certificateOrder->update(['status' => 'paid']);

            Log::error('Razorpay refund failed for a certificate order.', [
                'certificate_order_id' => $certificateOrder->id,
                'exception' => $exception->getMessage(),
            ]);

            return back()->with('status', 'Refund failed: '.$exception->getMessage());
        }

        $certificateOrder->update([
            'razorpay_refund_id' => $refund['id'],
            'refunded_at' => now(),
            'refunded_by' => request()->user()->id,
        ]);

        return back()->with('status', 'Refund issued.');
    }
}
