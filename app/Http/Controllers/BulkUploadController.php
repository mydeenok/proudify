<?php

namespace App\Http\Controllers;

use App\Models\CertificateBatch;
use App\Services\BulkUploadWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkUploadController extends Controller
{
    /**
     * Step 1: pick a template (Livewire wizard mount).
     */
    public function selectTemplate(): View
    {
        return view('bulk-upload.wizard', [
            'mode' => 'tenant',
            'step' => 'template',
        ]);
    }

    /**
     * A user sees only their own batches; an admin sees every batch (any
     * org), since admin issuance runs on behalf of other users and would
     * otherwise have no way back to a batch once its status page is gone.
     */
    public function history(): View
    {
        return view('bulk-upload.history');
    }

    /**
     * Step 2: upload the CSV/XLSX (Livewire wizard mount).
     */
    public function create(Request $request): View
    {
        $templateId = $request->integer('template');

        return view('bulk-upload.wizard', [
            'mode' => 'tenant',
            'step' => $templateId > 0 ? 'upload' : 'template',
            'templateId' => $templateId > 0 ? $templateId : null,
        ]);
    }

    public function store(Request $request, BulkUploadWizardService $wizard): RedirectResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $batch = $wizard->createTenantBatch(
            $request->user(),
            (int) $validated['template_id'],
            $request->file('file'),
        );

        return redirect()->route('bulk-upload.map-columns', $batch);
    }

    /**
     * Step 3: map spreadsheet columns (Livewire wizard resume).
     */
    public function mapColumns(Request $request, CertificateBatch $batch): View
    {
        $this->authorizeAccess($request, $batch);

        return view('bulk-upload.wizard', [
            'mode' => $request->user()->isAdmin() ? 'admin' : 'tenant',
            'step' => 'map',
            'batchId' => $batch->id,
        ]);
    }

    public function storeMapping(Request $request, CertificateBatch $batch, BulkUploadWizardService $wizard): RedirectResponse
    {
        $this->authorizeAccess($request, $batch);

        $validated = $request->validate([
            'mapping' => ['required', 'array'],
            'mapping.title' => ['required', 'integer'],
            'mapping.recipient_name' => ['required', 'integer'],
            'mapping.recipient_email' => ['required', 'integer'],
            'mapping.description' => ['nullable', 'integer'],
            'mapping.date_of_issue' => ['required', 'integer'],
            'mapping.date_of_expiry' => ['nullable', 'integer'],
        ]);

        try {
            $wizard->applyMapping($batch, $validated['mapping']);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('bulk-upload.review', $batch);
    }

    /**
     * Step 4: review counts before committing to issuance.
     */
    public function review(Request $request, CertificateBatch $batch): View
    {
        $this->authorizeAccess($request, $batch);

        return view('bulk-upload.wizard', [
            'mode' => $request->user()->isAdmin() ? 'admin' : 'tenant',
            'step' => 'review',
            'batchId' => $batch->id,
        ]);
    }

    /**
     * The live review UI drives confirmation through the Livewire wizard's
     * own confirm() action (see BulkUploadWizard), which is where the
     * payment gate actually lives. This plain route stays as a fallback
     * entry point, so BulkUploadWizardService::confirm() enforces the same
     * "paid order required unless admin-issued" guard regardless of which
     * one is hit.
     */
    public function confirm(Request $request, CertificateBatch $batch, BulkUploadWizardService $wizard): RedirectResponse
    {
        $this->authorizeAccess($request, $batch);

        try {
            $wizard->confirm($batch);
        } catch (ValidationException $exception) {
            return back()->withErrors($exception->errors());
        }

        return redirect()->route('bulk-upload.status', $batch);
    }

    public function status(Request $request, CertificateBatch $batch): View
    {
        $this->authorizeAccess($request, $batch);

        return view('bulk-upload.status', ['batch' => $batch]);
    }

    public function statusData(Request $request, CertificateBatch $batch)
    {
        $this->authorizeAccess($request, $batch);

        // Last few rows the queue actually touched, newest first - gives
        // the status page something to show beyond a percentage while a
        // batch is mid-flight, since chunks often finish in well under a
        // second and a single "0% -> done" jump reads as broken.
        $recentActivity = $batch->items()
            ->whereIn('status', ['succeeded', 'failed'])
            ->latest('updated_at')
            ->limit(8)
            ->get(['status', 'row_data', 'error_message'])
            ->map(fn ($item) => [
                'status' => $item->status,
                'recipientName' => $item->row_data['recipient_name'] ?? 'Unknown recipient',
                'errorMessage' => $item->error_message,
            ]);

        // camelCase here to match the Alpine state in bulk-upload/status.blade.php
        // exactly, since Object.assign(this, data) does a literal key merge
        // with no snake_case-to-camelCase translation.
        return response()->json([
            'status' => $batch->status,
            'totalRows' => $batch->total_rows,
            'processedRows' => $batch->processed_rows,
            'succeededRows' => $batch->succeeded_rows,
            'failedRows' => $batch->failed_rows,
            'progressPercent' => $batch->progressPercent(),
            'finished' => $batch->isFinished(),
            'errorReportAvailable' => (bool) $batch->error_report_path,
            'recentActivity' => $recentActivity,
        ]);
    }

    public function downloadErrorReport(Request $request, CertificateBatch $batch)
    {
        $this->authorizeAccess($request, $batch);

        abort_unless($batch->error_report_path, 404);

        return Storage::disk('local')->download($batch->error_report_path, "batch-{$batch->id}-errors.csv");
    }

    private function authorizeAccess(Request $request, CertificateBatch $batch): void
    {
        abort_unless(
            $batch->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }
}
