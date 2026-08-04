<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BulkUploadWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin issuance skips the single-org context of the user-facing wizard —
 * an admin picks WHICH org/user the batch is issued on behalf of first,
 * then reuses the exact same map-columns/review/confirm/status routes as
 * the user flow (those routes already allow admin access to any batch).
 */
class BulkUploadController extends Controller
{
    public function create(): View
    {
        return view('bulk-upload.wizard', [
            'mode' => 'admin',
            'step' => 'setup',
        ]);
    }

    public function store(Request $request, BulkUploadWizardService $wizard): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $batch = $wizard->createAdminBatch(
            $request->user(),
            (int) $validated['user_id'],
            (int) $validated['template_id'],
            $request->file('file'),
        );

        return redirect()->route('bulk-upload.map-columns', $batch);
    }
}
