<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BillingSettingController extends Controller
{
    public function edit(): View
    {
        return view('admin.billing-settings.edit', ['settings' => BillingSetting::current()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'price_per_certificate_inr' => ['required', 'numeric', 'min:0.01'],
            'bulk_discount_threshold' => ['required', 'integer', 'min:0'],
            'bulk_discount_percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $settings = BillingSetting::current();
        $settings->update([
            ...$validated,
            'updated_by' => $request->user()->id,
        ]);

        return redirect()->route('admin.billing-settings.edit')->with('status', 'Pricing settings updated.');
    }
}
