<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserSubscription;
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
    public function index(Request $request): View
    {
        $subscriptions = UserSubscription::query()
            ->with(['user', 'subscription'])
            ->when($request->string('search')->isNotEmpty(), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('user', fn ($q) => $q
                    ->where('organization_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest('start_date')
            ->paginate(15)
            ->withQueryString();

        return view('admin.user-subscriptions.index', ['subscriptions' => $subscriptions]);
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

        return back()->with('status', 'Subscription cancelled.');
    }
}
