<?php

namespace App\Http\Controllers;

use App\Jobs\Bulk\DispatchCertificateBatchJob;
use App\Models\CertificateBatch;
use App\Models\Template;
use App\Services\BulkUploadIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BulkUploadController extends Controller
{
    /**
     * Step 1: pick a template.
     */
    public function selectTemplate(): View
    {
        $templates = Template::active()->orderBy('name')->get();

        return view('bulk-upload.select-template', ['templates' => $templates]);
    }

    /**
     * Step 2: upload the CSV/XLSX.
     */
    public function create(Request $request): View
    {
        $template = Template::active()->findOrFail($request->integer('template'));

        return view('bulk-upload.upload', ['template' => $template]);
    }

    public function store(Request $request, BulkUploadIngestService $ingestService): RedirectResponse
    {
        $validated = $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $template = Template::active()->findOrFail($validated['template_id']);

        $tempPath = $request->file('file')->store('bulk-upload-temp', 'local');

        $batch = CertificateBatch::create([
            'user_id' => $request->user()->id,
            'template_id' => $template->id,
            'original_filename' => $request->file('file')->getClientOriginalName(),
            'temp_upload_path' => $tempPath,
            'status' => 'mapping',
        ]);

        return redirect()->route('bulk-upload.map-columns', $batch);
    }

    /**
     * Step 3: map spreadsheet columns to certificate fields.
     */
    public function mapColumns(Request $request, CertificateBatch $batch, BulkUploadIngestService $ingestService): View
    {
        $this->authorizeAccess($request, $batch);

        $headers = $ingestService->parseHeaders(Storage::disk('local')->path($batch->temp_upload_path));

        return view('bulk-upload.map-columns', ['batch' => $batch, 'headers' => $headers]);
    }

    public function storeMapping(Request $request, CertificateBatch $batch, BulkUploadIngestService $ingestService): RedirectResponse
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

        $batch->forceFill(['column_mapping' => $validated['mapping']])->save();

        try {
            $ingestService->ingestRows($batch, $validated['mapping']);
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

        $pendingCount = $batch->items()->where('status', 'pending')->count();
        $skippedCount = $batch->items()->where('status', 'skipped')->count();
        $failedCount = $batch->items()->where('status', 'failed')->count();

        return view('bulk-upload.review', compact('batch', 'pendingCount', 'skippedCount', 'failedCount'));
    }

    public function confirm(Request $request, CertificateBatch $batch): RedirectResponse
    {
        $this->authorizeAccess($request, $batch);

        DispatchCertificateBatchJob::dispatch($batch);

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
        ]);
    }

    public function downloadErrorReport(Request $request, CertificateBatch $batch)
    {
        $this->authorizeAccess($request, $batch);

        abort_unless($batch->error_report_path, 404);

        return Storage::disk('public')->download($batch->error_report_path, "batch-{$batch->id}-errors.csv");
    }

    private function authorizeAccess(Request $request, CertificateBatch $batch): void
    {
        abort_unless(
            $batch->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }
}
