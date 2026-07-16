<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountApprovedNotification;
use App\Notifications\AccountRejectedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $users = User::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('organization_name', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->string('role')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', ['users' => $users]);
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        abort_if($user->isAdmin(), 403, 'Admins cannot be suspended.');

        $user->forceFill(['status' => 'suspended'])->save();

        return back()->with('status', "{$user->name} has been suspended.");
    }

    public function reactivate(User $user): RedirectResponse
    {
        $user->forceFill(['status' => 'active'])->save();

        return back()->with('status', "{$user->name} has been reactivated.");
    }

    /**
     * Gate 3 review queue: registrants who verified their OTP and are
     * waiting on an admin to trust the organization.
     */
    public function unapproved(): View
    {
        $users = User::where('status', 'pending_approval')
            ->orderBy('created_at')
            ->paginate(20);

        return view('admin.users.unapproved', ['users' => $users]);
    }

    public function approve(Request $request, User $user): RedirectResponse
    {
        abort_unless($user->status === 'pending_approval', 404);

        $user->forceFill([
            'status' => 'active',
            'approved_at' => now(),
            'approved_by' => $request->user()->id,
        ])->save();

        try {
            $user->notify(new AccountApprovedNotification);
        } catch (Throwable $exception) {
            Log::error('Failed to send account-approved email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }

        return back()->with('status', "{$user->name} has been approved.");
    }

    /**
     * Rejection is destructive (matches the reference app's behavior) —
     * the account row is removed rather than flagged, since a rejected
     * registrant has no certificates or history to preserve yet.
     */
    public function reject(User $user): RedirectResponse
    {
        abort_unless($user->status === 'pending_approval', 404);

        try {
            $user->notify(new AccountRejectedNotification);
        } catch (Throwable $exception) {
            Log::error('Failed to send account-rejected email.', ['user_id' => $user->id, 'exception' => $exception->getMessage()]);
        }

        $user->delete();

        return back()->with('status', 'The registration request has been rejected.');
    }
}
