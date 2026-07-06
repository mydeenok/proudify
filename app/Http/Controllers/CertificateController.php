<?php

namespace App\Http\Controllers;

use App\Actions\Certificates\IssueSingleCertificateAction;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\Template;
use App\Services\CertificateRenderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class CertificateController extends Controller
{
    public function index(Request $request): View
    {
        $query = $request->user()
            ->certificates()
            ->with('template')
            ->latest();

        if ($search = $request->string('search')->trim()->toString()) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('recipient_name', 'like', "%{$search}%")
                    ->orWhere('recipient_email', 'like', "%{$search}%");
            });
        }

        if ($status = $request->string('status')->toString()) {
            match ($status) {
                'active' => $query->where('status', '!=', 'revoked')
                    ->where(fn ($q) => $q->whereNull('date_of_expiry')->orWhere('date_of_expiry', '>=', now()->toDateString())),
                'expired' => $query->where('status', '!=', 'revoked')
                    ->whereNotNull('date_of_expiry')
                    ->where('date_of_expiry', '<', now()->toDateString()),
                'revoked' => $query->where('status', 'revoked'),
                default => null,
            };
        }

        $certificates = $query->paginate(10)->withQueryString();

        return view('certificates.index', ['certificates' => $certificates]);
    }

    public function create(Request $request, CertificateRenderService $renderService): View
    {
        $template = Template::active()->findOrFail($request->integer('template'));

        // Same real-template render the standalone Preview Certificate page
        // and the "previewRender" endpoint use — the inline panel used to
        // show a generic mockup card that didn't match what would actually
        // get issued.
        $initialPreviewHtml = $renderService->renderPreviewHtml($template, $request->user(), [
            'date_of_issue' => now()->toDateString(),
        ]);

        return view('certificates.create', [
            'template' => $template,
            'initialPreviewHtml' => $initialPreviewHtml,
            'customFields' => $template->editableCustomFields(),
        ]);
    }

    /**
     * Opens in a new tab from the create-certificate form. Renders the
     * template's real html_content with whatever the user has typed so
     * far (no Certificate row exists yet), so it's an honest preview of
     * the actual issued design rather than a generic mockup card.
     */
    public function preview(Request $request, CertificateRenderService $renderService): View
    {
        [$template, $validated] = $this->resolveTemplateAndValidatePreview($request);

        $html = $renderService->renderPreviewHtml($template, $request->user(), $validated);

        return view('certificates.preview', [
            'template' => $template,
            'customFields' => $template->editableCustomFields(),
            'formData' => $validated,
            'initialHtml' => $html,
        ]);
    }

    /**
     * Re-render endpoint the preview page's iframe polls (debounced) as
     * the user keeps editing fields, so the preview stays live without a
     * full page reload.
     */
    public function previewRender(Request $request, CertificateRenderService $renderService): Response
    {
        [$template, $validated] = $this->resolveTemplateAndValidatePreview($request);

        $html = $renderService->renderPreviewHtml($template, $request->user(), $validated);

        return response($html)->header('Content-Type', 'text/html');
    }

    /**
     * The custom-field validation rules depend on the template's own
     * schema, so template_id is resolved first (its own tiny validation
     * pass) before the rest of the request can be validated against rules
     * built from that specific template.
     *
     * @return array{0: Template, 1: array<string, mixed>}
     */
    private function resolveTemplateAndValidatePreview(Request $request): array
    {
        $templateId = $request->validate(['template_id' => ['required', 'exists:templates,id']])['template_id'];
        $template = Template::active()->findOrFail($templateId);

        $validated = $request->validate($this->previewValidationRules($template));

        return [$template, $validated];
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function previewValidationRules(Template $template): array
    {
        $rules = [
            'template_id' => ['required', 'exists:templates,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'date_of_issue' => ['nullable', 'date'],
            'date_of_expiry' => ['nullable', 'date'],
        ];

        // Image-type custom fields have no uploaded file to preview before
        // a Certificate row exists, so only text fields are validated here
        // — see CertificateRenderService::stripUnfilledCustomImagePlaceholders.
        foreach ($template->editableCustomFields() as $field) {
            if ($field['type'] === 'text') {
                $rules["custom_fields.{$field['key']}"] = ['nullable', 'string', 'max:500'];
            }
        }

        return $rules;
    }

    public function store(Request $request, IssueSingleCertificateAction $action): RedirectResponse
    {
        $template = Template::active()->findOrFail($request->integer('template_id'));

        $validated = $request->validate($this->storeValidationRules($template));
        $validated['custom_image_fields'] = $this->storeUploadedCustomImages($request, $template);

        try {
            $certificate = $action->execute($request->user(), $template, $validated);
        } catch (SubscriptionQuotaExceededException $exception) {
            return redirect()->route('pricing')->with('quota_message', $exception->getMessage());
        }

        return redirect()->route('certificates.show', $certificate)->with('status', 'Certificate is being generated — it will be ready in a few moments.');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function storeValidationRules(Template $template): array
    {
        $rules = [
            'template_id' => ['required', 'exists:templates,id'],
            'title' => ['required', 'string', 'max:150'],
            'recipient_name' => ['required', 'string', 'max:150'],
            'recipient_email' => ['required', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'date_of_issue' => ['required', 'date'],
            'date_of_expiry' => ['nullable', 'date', 'after:date_of_issue'],
        ];

        foreach ($template->editableCustomFields() as $field) {
            $presence = $field['required'] ? 'required' : 'nullable';

            $rules[$field['type'] === 'image' ? "custom_image_fields.{$field['key']}" : "custom_fields.{$field['key']}"] = $field['type'] === 'image'
                ? [$presence, 'image', 'max:2048']
                : [$presence, 'string', 'max:500'];
        }

        return $rules;
    }

    /**
     * Uploaded custom-field images are stored the same way profile
     * signature/logo uploads already are (plain public-disk storage, no
     * media-library dependency) — see ProfileController. Re-derives the
     * key => path map fresh from disk rather than trusting $validated's
     * raw UploadedFile instances, since Certificate::custom_image_fields
     * stores paths, not file objects.
     *
     * @return array<string, string>
     */
    private function storeUploadedCustomImages(Request $request, Template $template): array
    {
        $paths = [];

        foreach ($template->editableCustomFields() as $field) {
            if ($field['type'] !== 'image') {
                continue;
            }

            if ($file = $request->file("custom_image_fields.{$field['key']}")) {
                $paths[$field['key']] = $file->store('certificates/custom-fields', 'public');
            }
        }

        return $paths;
    }

    public function show(Request $request, Certificate $certificate): View
    {
        $this->authorizeAccess($request, $certificate);

        return view('certificates.show', ['certificate' => $certificate->load(['template', 'user'])]);
    }

    public function status(Request $request, Certificate $certificate): JsonResponse
    {
        $this->authorizeAccess($request, $certificate);

        $certificate->refresh();
        $certificate->loadMissing('template', 'user');

        $this->queueMissingGenerationJobs($certificate);

        return response()->json($this->statusPayload($certificate));
    }

    public function regenerate(Request $request, Certificate $certificate): JsonResponse
    {
        $this->authorizeAccess($request, $certificate);

        $certificate->refresh();

        abort_unless($certificate->qr_code_path, 422, 'QR code is not ready yet.');

        Bus::chain([
            new GenerateCertificatePdfJob($certificate),
            new ConvertCertificatePdfToImageJob($certificate),
        ])->dispatch();

        $certificate->forceFill(['image_generation_status' => 'processing'])->save();

        return response()->json([
            ...$this->statusPayload($certificate->fresh()),
            'message' => 'Certificate assets are being regenerated.',
        ]);
    }

    public function download(Request $request, Certificate $certificate)
    {
        $this->authorizeAccess($request, $certificate);

        abort_unless($certificate->pdf_path, 404, 'The PDF is still being generated.');

        return Storage::disk('public')->download($certificate->pdf_path, "{$certificate->title}.pdf");
    }

    private function authorizeAccess(Request $request, Certificate $certificate): void
    {
        abort_unless(
            $certificate->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }

    private function queueMissingGenerationJobs(Certificate $certificate): void
    {
        if ($certificate->image_generation_status === 'processing') {
            return;
        }

        if ($certificate->qr_code_path && ! $certificate->pdf_path) {
            GenerateCertificatePdfJob::dispatch($certificate);

            return;
        }

        if ($certificate->pdf_path && ! $certificate->image_path && $certificate->image_generation_status !== 'failed') {
            ConvertCertificatePdfToImageJob::dispatch($certificate);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(Certificate $certificate): array
    {
        $cacheBust = $certificate->updated_at?->timestamp ?? time();

        return [
            'ready' => (bool) $certificate->image_path,
            'image_url' => $certificate->image_path
                ? Storage::url($certificate->image_path).'?v='.$cacheBust
                : null,
            'pdf_ready' => (bool) $certificate->pdf_path,
            'image_generation_status' => $certificate->image_generation_status,
            'display_status' => $certificate->displayStatus(),
            'verification_code' => $certificate->verification_code,
            'template_name' => $certificate->template->name,
            'qr_url' => $certificate->qr_code_path
                ? Storage::url($certificate->qr_code_path).'?v='.$cacheBust
                : null,
        ];
    }
}
