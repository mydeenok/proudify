<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
use App\Notifications\AdminSubscriptionCancelledNotification;
use App\Support\NotifyAdmins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reference app's equivalent module was confirmed read-only at the
 * routing level — no edit/cancel/extend handler existed at all. This is
 * the fix: real actions an admin can take on a subscriber's behalf.
 */
class UserSubscriptionController extends Controller
{
    public function index(): View
    {
        return view('admin.user-subscriptions.index');
    }

    public function edit(UserSubscription $userSubscription): View
    {
        return view('admin.user-subscriptions.edit', ['userSubscription' => $userSubscription]);
    }

    public function update(Request $request, UserSubscription $userSubscription): RedirectResponse
    {
        $validated = $request->validate([
            'certificates_limit' => ['required', 'integer', 'min:0'],
            'users_limit' => ['required', 'integer', 'min:0'],
        ]);

        $userSubscription->update($validated);

        return redirect()->route('admin.user-subscriptions.index')->with('status', 'Subscription updated.');
    }

    public function extend(Request $request, UserSubscription $userSubscription): RedirectResponse
    {
        $validated = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:365'],
        ]);

        $userSubscription->update([
            'end_date' => $userSubscription->end_date->addDays($validated['days']),
            'is_active' => true,
        ]);

        return back()->with('status', "Extended by {$validated['days']} days.");
    }

    public function cancel(UserSubscription $userSubscription): RedirectResponse
    {
        $userSubscription->update(['is_active' => false, 'auto_renew' => false]);
        $userSubscription->loadMissing('user', 'subscription');

        // Deliberately NOT sending SubscriptionCancelledNotification to
        // the user anymore - that email's subject line is "Your Proudify
        // subscription was cancelled," implying they're losing access to
        // something. Since SubscriptionQuotaService has no callers
        // anywhere in certificate/bulk issuance, this action has never
        // actually restricted the user - sending that email would tell
        // them something false. The admin-facing alert below stays: it's
        // a factual "an admin cancelled this record" note, not a claim
        // about the user's access changing.
        NotifyAdmins::notify(
            new AdminSubscriptionCancelledNotification($userSubscription),
            'Failed to send admin subscription-cancelled alert.',
            ['user_subscription_id' => $userSubscription->id],
        );

        return back()->with('status', 'Subscription marked cancelled (informational only — does not restrict issuance).');
    }
}
