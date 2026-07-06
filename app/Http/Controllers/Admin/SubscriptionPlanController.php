<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SubscriptionPlanController extends Controller
{
    public function index(): View
    {
        $plans = Subscription::orderByDesc('is_default_free_plan')->orderBy('sort_order')->get();

        return view('admin.subscriptions.index', ['plans' => $plans]);
    }

    public function create(): View
    {
        return view('admin.subscriptions.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated) {
            $this->enforceSingleFreePlan($validated);

            Subscription::create($validated);
        });

        return redirect()->route('admin.subscriptions.index')->with('status', 'Plan created.');
    }

    public function edit(Subscription $subscription): View
    {
        return view('admin.subscriptions.edit', ['plan' => $subscription]);
    }

    public function update(Request $request, Subscription $subscription): RedirectResponse
    {
        $validated = $this->validated($request, $subscription);

        DB::transaction(function () use ($validated, $subscription) {
            $this->enforceSingleFreePlan($validated, $subscription->id);

            $subscription->update($validated);
        });

        return redirect()->route('admin.subscriptions.index')->with('status', 'Plan updated.');
    }

    public function destroy(Subscription $subscription): RedirectResponse
    {
        $subscription->delete();

        return back()->with('status', 'Plan deleted.');
    }

    public function toggleStatus(Subscription $subscription): RedirectResponse
    {
        $subscription->update(['is_active' => ! $subscription->is_active]);

        return back()->with('status', $subscription->is_active ? 'Plan activated.' : 'Plan deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Subscription $subscription = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z]+(?:\s[A-Za-z]+)*$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'certificates_per_month' => ['required', 'integer', 'min:0'],
            'certificates_per_year' => ['required', 'integer', 'min:0'],
            'users_per_month' => ['required', 'integer', 'min:0'],
            'users_per_year' => ['required', 'integer', 'min:0'],
            'cost_month_inr' => ['required', 'numeric', 'min:0'],
            'cost_year_inr' => ['required', 'numeric', 'min:0'],
            'cost_month_usd' => ['required', 'numeric', 'min:0'],
            'cost_year_usd' => ['required', 'numeric', 'min:0'],
            'is_default_free_plan' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
        ]);
    }

    /**
     * Enforces the "at most one default free plan" invariant in the Action
     * layer since MySQL/MariaDB has no native partial/filtered unique
     * index — this transaction+lock is the actual guarantee.
     *
     * @param  array<string, mixed>  $validated
     */
    private function enforceSingleFreePlan(array $validated, ?int $excludingId = null): void
    {
        if (empty($validated['is_default_free_plan'])) {
            return;
        }

        Subscription::where('is_default_free_plan', true)
            ->when($excludingId, fn ($query) => $query->whereKeyNot($excludingId))
            ->lockForUpdate()
            ->update(['is_default_free_plan' => false]);
    }
}
