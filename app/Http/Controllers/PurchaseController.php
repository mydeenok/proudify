<?php

namespace App\Http\Controllers;

use App\Actions\Subscriptions\ActivateFreePlanAction;
use App\Models\Subscription;
use App\Models\User;
use App\Models\UserSubscription;
use App\Notifications\AdminNewPurchaseNotification;
use App\Notifications\PaymentSuccessfulNotification;
use App\Services\GeoLocationService;
use App\Services\RazorpayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Razorpay\Api\Errors\Error as RazorpayError;

class PurchaseController extends Controller
{
    public function show(Request $request, Subscription $subscription): View
    {
        $period = $request->string('period')->value() === 'yearly' ? 'yearly' : 'monthly';
        $currency = app(GeoLocationService::class)->currencyFor($request->ip());

        return view('purchase.show', [
            'subscription' => $subscription,
            'period' => $period,
            'currency' => $currency,
            'amount' => $subscription->priceFor($period, $currency),
        ]);
    }

    /**
     * Free-plan activation only — paid plans must go through
     * createOrder/verifyPayment, matching the reference app's rule that
     * money never moves without a real payment gateway confirmation.
     */
    public function process(Request $request, Subscription $subscription, ActivateFreePlanAction $action): RedirectResponse
    {
        if (! $subscription->isFree()) {
            return back()->withErrors(['plan' => 'This plan requires payment. Please use the checkout flow.']);
        }

        try {
            $action->execute($request->user());
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('dashboard')->with('status', 'Your free plan is now active.');
    }

    public function createOrder(Request $request, Subscription $subscription, RazorpayService $razorpay, GeoLocationService $geo): JsonResponse
    {
        $period = $request->string('period')->value() === 'yearly' ? 'yearly' : 'monthly';
        $currency = $geo->currencyFor($request->ip());
        $amount = $subscription->priceFor($period, $currency);

        if ($amount <= 0) {
            return response()->json(['message' => 'This plan is free — use the activate endpoint instead.'], 422);
        }

        try {
            $order = $razorpay->createOrder($amount, $currency);
        } catch (RazorpayError $exception) {
            Log::error('Razorpay order creation failed.', [
                'subscription_id' => $subscription->id,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to start checkout right now. Please try again shortly.'], 502);
        }

        return response()->json([
            ...$order,
            'key' => config('services.razorpay.key'),
            'period' => $period,
        ]);
    }

    public function verifyPayment(Request $request, Subscription $subscription, RazorpayService $razorpay, GeoLocationService $geo): JsonResponse
    {
        $validated = $request->validate([
            'razorpay_order_id' => ['required', 'string'],
            'razorpay_payment_id' => ['required', 'string'],
            'razorpay_signature' => ['required', 'string'],
            'period' => ['required', 'in:monthly,yearly'],
        ]);

        // Idempotency: a client-side retry re-posting the same payment_id
        // must not create a second subscription row. The unique DB index
        // on razorpay_payment_id is the actual guarantee; this check just
        // gives a clean response instead of a constraint-violation error.
        $existing = UserSubscription::where('razorpay_payment_id', $validated['razorpay_payment_id'])->first();
        if ($existing) {
            return response()->json(['success' => true, 'redirect' => route('dashboard')]);
        }

        $signatureValid = $razorpay->verifySignature(
            $validated['razorpay_order_id'],
            $validated['razorpay_payment_id'],
            $validated['razorpay_signature'],
        );

        if (! $signatureValid) {
            return response()->json(['message' => 'Payment verification failed.'], 422);
        }

        $currency = $geo->currencyFor($request->ip());
        $amount = $subscription->priceFor($validated['period'], $currency);
        $limits = $subscription->limitsFor($validated['period']);
        $now = now();

        $userSubscription = UserSubscription::create([
            'user_id' => $request->user()->id,
            'subscription_id' => $subscription->id,
            'certificates_limit' => $limits['certificates'],
            'users_limit' => $limits['users'],
            'current_period_started_at' => $now,
            'billing_period' => $validated['period'],
            'amount_paid' => $amount,
            'currency' => $currency,
            'payment_status' => 'completed',
            'razorpay_order_id' => $validated['razorpay_order_id'],
            'razorpay_payment_id' => $validated['razorpay_payment_id'],
            'razorpay_signature' => $validated['razorpay_signature'],
            'payment_verified_at' => $now,
            'start_date' => $now,
            'end_date' => $validated['period'] === 'yearly' ? $now->copy()->addYear() : $now->copy()->addMonth(),
            'is_active' => true,
            'auto_renew' => false,
            'payment_details' => [
                'subscription_type' => $validated['period'],
                'original_amount' => $amount,
                'currency' => $currency,
                'user_ip' => $request->ip(),
            ],
        ]);

        $userSubscription->setRelation('user', $request->user());
        $userSubscription->setRelation('subscription', $subscription);

        $request->user()->notify(new PaymentSuccessfulNotification($userSubscription));
        User::admins()->get()->each(fn (User $admin) => $admin->notify(new AdminNewPurchaseNotification($userSubscription)));

        return response()->json(['success' => true, 'redirect' => route('dashboard')]);
    }
}
