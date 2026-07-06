<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
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
        $users = User::where('role', 'user')->orderBy('organization_name')->get();
        $templates = Template::active()->orderBy('name')->get();

        return view('admin.bulk-upload.create', compact('users', 'templates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'template_id' => ['required', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $tempPath = $request->file('file')->store('bulk-upload-temp', 'local');

        $batch = CertificateBatch::create([
            'user_id' => $validated['user_id'],
            'template_id' => $validated['template_id'],
            'issued_by' => $request->user()->id,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'temp_upload_path' => $tempPath,
            'status' => 'mapping',
        ]);

        return redirect()->route('bulk-upload.map-columns', $batch);
    }
}
