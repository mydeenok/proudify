<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class AdminCertificateController extends Controller
{
    public function index(): View
    {
        return view('admin.certificates.index');
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        abort_if($certificate->status === 'revoked', 422, 'This certificate is already revoked.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $certificate->forceFill([
            'status' => 'revoked',
            'revoked_at' => now(),
            'revoked_reason' => $validated['reason'],
            'revoked_by' => $request->user()->id,
        ])->save();

        return back()->with('status', 'Certificate revoked.');
    }

    public function bulkDownload(Request $request)
    {
        $validated = $request->validate([
            'certificate_ids' => ['required', 'array', 'min:1'],
            'certificate_ids.*' => ['integer', 'exists:certificates,id'],
        ]);

        $certificates = Certificate::whereIn('id', $validated['certificate_ids'])
            ->whereNotNull('pdf_path')
            ->get();

        abort_if($certificates->isEmpty(), 404, 'None of the selected certificates have a generated PDF yet.');

        $zipRelativePath = 'tmp/certificates-'.now()->timestamp.'-'.uniqid().'.zip';
        $zipFullPath = Storage::disk('local')->path($zipRelativePath);
        Storage::disk('local')->makeDirectory('tmp');

        $zip = new ZipArchive;
        $zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        foreach ($certificates as $certificate) {
            if (Storage::disk('local')->exists($certificate->pdf_path)) {
                $filename = Str::slug($certificate->recipient_name).'-'.$certificate->uuid.'.pdf';
                $zip->addFile(Storage::disk('local')->path($certificate->pdf_path), $filename);
            }
        }

        $zip->close();

        return response()->download($zipFullPath, 'certificates.zip')->deleteFileAfterSend();
    }
}
