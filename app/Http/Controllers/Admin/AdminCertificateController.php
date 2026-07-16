<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use ZipArchive;

class AdminCertificateController extends Controller
{
    public function index(Request $request): View
    {
        $certificates = Certificate::query()
            ->with(['user', 'template'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search')->value();
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%")
                        ->orWhere('verification_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('organization_name', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('status'), function ($query) use ($request) {
                match ($request->string('status')->toString()) {
                    'active' => $query->where('status', 'active')
                        ->where(fn ($q) => $q->whereNull('date_of_expiry')->orWhere('date_of_expiry', '>=', now()->toDateString()))
                        ->whereNotNull('pdf_path'),
                    'revoked' => $query->where('status', 'revoked'),
                    'expired' => $query->where('status', '!=', 'revoked')
                        ->whereNotNull('date_of_expiry')
                        ->where('date_of_expiry', '<', now()->toDateString()),
                    'pending' => $query->where('status', 'active')->whereNull('pdf_path'),
                    'failed' => $query->where('image_generation_status', 'failed'),
                    default => null,
                };
            })
            ->when($request->filled('template_id'), fn ($query) => $query->where('template_id', $request->integer('template_id')))
            ->when($request->string('period')->toString() === '30', fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->when($request->string('period')->toString() === '7', fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.certificates.index', [
            'certificates' => $certificates,
            'templates' => Template::orderBy('name')->get(['id', 'name']),
        ]);
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
