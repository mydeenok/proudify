<?php

namespace App\Http\Controllers;

use App\Actions\Certificates\IssueSingleCertificateAction;
use App\Exceptions\SubscriptionQuotaExceededException;
use App\Jobs\Certificates\ConvertCertificatePdfToImageJob;
use App\Jobs\Certificates\GenerateCertificatePdfJob;
use App\Models\Certificate;
use App\Models\CertificateDraft;
use App\Models\Template;
use App\Services\CertificateRenderService;
use App\Services\TemplateBackgroundImportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CertificateController extends Controller
{
    public function index(): View
    {
        return view('certificates.index');
    }

    public function create(Request $request, CertificateRenderService $renderService): View
    {
        $template = Template::active()->findOrFail($request->integer('template'));
        $user = $request->user();
        $issueAnother = $request->boolean('issue_another');

        $draft = CertificateDraft::query()
            ->where('user_id', $user->id)
            ->where('template_id', $template->id)
            ->first();

        $draftPayload = is_array($draft?->payload) ? $draft->payload : [];

        if ($issueAnother) {
            unset($draftPayload['recipient_name'], $draftPayload['recipient_email']);
        }

        // Same real-template render the standalone Preview Certificate page
        // and the "previewRender" endpoint use — the inline panel used to
        // show a generic mockup card that didn't match what would actually
        // get issued. Canvas templates paint via the Node engine as
        // the issued PDF (image/png); legacy HTML templates keep srcdoc.
        $previewForm = array_merge(
            ['date_of_issue' => now()->toDateString()],
            array_intersect_key($draftPayload, array_flip([
                'title', 'recipient_name', 'description', 'date_of_issue', 'date_of_expiry', 'custom_fields',
            ])),
        );

        $preview = $renderService->renderPreview($template, $user, $previewForm);

        $elements = is_array($template->canvas_json['elements'] ?? null)
            ? $template->canvas_json['elements']
            : [];

        $templateNeedsLogo = collect($elements)->contains(
            fn (array $el) => ($el['type'] ?? null) === 'company_logo'
                || ($el['binding'] ?? null) === 'company_logo'
                || preg_match('/^company_logo(_\d+)?$/', (string) ($el['binding'] ?? '')) === 1
        );
        $templateNeedsSignature = collect($elements)->contains(
            fn (array $el) => ($el['type'] ?? null) === 'signature'
                || ($el['binding'] ?? null) === 'signature'
        );

        $missingSignature = $templateNeedsSignature && blank($user->signature_path);
        $missingLogo = $templateNeedsLogo && empty($user->org_logos);

        $fieldHighlights = collect($elements)
            ->filter(fn (array $el) => filled($el['binding'] ?? null))
            ->map(fn (array $el) => [
                'binding' => $el['binding'],
                'xPercent' => (float) ($el['xPercent'] ?? 0),
                'yPercent' => (float) ($el['yPercent'] ?? 0),
                'widthPercent' => (float) ($el['widthPercent'] ?? 10),
                'heightPercent' => (float) ($el['heightPercent'] ?? 10),
            ])
            ->values()
            ->all();

        return view('certificates.create', [
            'template' => $template,
            'previewMode' => $preview['mode'],
            'initialPreviewHtml' => $preview['mode'] === 'html' ? $preview['body'] : null,
            'initialPreviewDataUri' => $preview['mode'] === 'canvas'
                ? 'data:image/png;base64,'.base64_encode($preview['body'])
                : null,
            'customFields' => $template->editableCustomFields(),
            'draftPayload' => $draftPayload,
            'hasDraft' => $draft !== null && ! $issueAnother,
            'missingSignature' => $missingSignature,
            'missingLogo' => $missingLogo,
            'templateNeedsLogo' => $templateNeedsLogo,
            'templateNeedsSignature' => $templateNeedsSignature,
            'profileHasSignature' => filled($user->signature_path),
            'profileHasLogo' => ! empty($user->org_logos),
            'fieldHighlights' => $fieldHighlights,
            'issueAnother' => $issueAnother,
            'draftSaveUrl' => route('certificates.drafts.save'),
            'draftDeleteUrl' => route('certificates.drafts.destroy'),
        ]);
    }

    /**
     * Same issuance flow as create()/store(), but the design itself is the
     * form: the real rendered certificate (renderPreviewHtml — same as the
     * plain form's live preview) fills the canvas, with an editable input
     * absolutely-positioned over every field the user is allowed to fill
     * in, at that field's exact canvas_json coordinates. No new layout is
     * ever persisted per certificate — position/style stay locked to the
     * template's own design; only content changes. Submission collects
     * those values into the exact field names storeValidationRules()
     * already expects, so it POSTs to the unchanged store() endpoint below
     * with zero new backend logic.
     *
     * Templates with no canvas_json yet (hand-written HTML never opened in
     * the builder) have no element coordinates to overlay inputs onto, so
     * this falls back to the plain form instead of showing a design with
     * nothing editable on it.
     */
    public function createCanvas(Request $request, Template $template, CertificateRenderService $renderService): View|RedirectResponse
    {
        abort_unless($template->is_active, 404);

        if (empty($template->canvas_json['elements'])) {
            return redirect()->route('certificates.create', ['template' => $template->id]);
        }

        $customFieldKeys = collect($template->editableCustomFields())->pluck('key')->all();
        $editableSystemBindings = ['title', 'recipient_name', 'description', 'date_of_issue', 'date_of_expiry'];

        $overlayElements = collect($template->canvas_json['elements'])
            ->filter(function (array $element) use ($editableSystemBindings, $customFieldKeys) {
                $binding = $element['binding'] ?? null;

                return $binding && (in_array($binding, $editableSystemBindings, true) || in_array($binding, $customFieldKeys, true));
            })
            ->values();

        // Text bindings the overlay will handle are rendered blank in the
        // background so the overlay input's own text is the only copy
        // visible — otherwise sample fallbacks ("Certificate Title" etc)
        // would show through underneath it.
        $preview = $renderService->renderPreview($template, $request->user(), [
            'title' => '',
            'recipient_name' => '',
            'description' => '',
        ]);

        return view('certificates.create-canvas', [
            'template' => $template,
            'customFields' => $template->editableCustomFields(),
            'overlayElements' => $overlayElements,
            'previewMode' => $preview['mode'],
            'initialPreviewHtml' => $preview['mode'] === 'html' ? $preview['body'] : null,
            'initialPreviewDataUri' => $preview['mode'] === 'canvas'
                ? 'data:image/png;base64,'.base64_encode($preview['body'])
                : null,
            'canvasWidth' => $template->orientation === 'portrait' ? 707 : 1000,
            'canvasHeight' => $template->orientation === 'portrait' ? 1000 : 707,
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

        $preview = $renderService->renderPreview($template, $request->user(), $validated);

        return view('certificates.preview', [
            'template' => $template,
            'customFields' => $template->editableCustomFields(),
            'formData' => $validated,
            'previewMode' => $preview['mode'],
            'initialHtml' => $preview['mode'] === 'html' ? $preview['body'] : null,
            'initialPreviewDataUri' => $preview['mode'] === 'canvas'
                ? 'data:image/png;base64,'.base64_encode($preview['body'])
                : null,
        ]);
    }

    /**
     * Re-render endpoint the preview surfaces poll (debounced) as the user
     * keeps editing fields. Returns image/png for canvas templates (same
     * painter as the issued PDF) or text/html for legacy templates —
     * Content-Type + X-Preview-Mode tell the client which surface to update.
     */
    public function previewRender(Request $request, CertificateRenderService $renderService): Response
    {
        [$template, $validated] = $this->resolveTemplateAndValidatePreview($request);

        $preview = $renderService->renderPreview($template, $request->user(), $validated);

        return response($preview['body'])
            ->header('Content-Type', $preview['contentType'])
            ->header('X-Preview-Mode', $preview['mode']);
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

        CertificateDraft::query()
            ->where('user_id', $request->user()->id)
            ->where('template_id', $template->id)
            ->delete();

        return redirect()
            ->route('certificates.show', $certificate)
            ->with('status', 'Certificate issued.')
            ->with('issued_template_id', $template->id);
    }

    public function saveDraft(Request $request): JsonResponse
    {
        $template = Template::active()->findOrFail($request->integer('template_id'));
        $validated = $request->validate($this->draftValidationRules($template));

        $draft = CertificateDraft::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'template_id' => $template->id,
            ],
            [
                'payload' => [
                    'title' => $validated['title'] ?? null,
                    'recipient_name' => $validated['recipient_name'] ?? null,
                    'recipient_email' => $validated['recipient_email'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'date_of_issue' => $validated['date_of_issue'] ?? null,
                    'date_of_expiry' => $validated['date_of_expiry'] ?? null,
                    'custom_fields' => $validated['custom_fields'] ?? [],
                ],
            ],
        );

        return response()->json([
            'status' => 'saved',
            'saved_at' => $draft->updated_at?->toIso8601String(),
        ]);
    }

    public function destroyDraft(Request $request): JsonResponse
    {
        $templateId = $request->validate([
            'template_id' => ['required', 'exists:templates,id'],
        ])['template_id'];

        CertificateDraft::query()
            ->where('user_id', $request->user()->id)
            ->where('template_id', $templateId)
            ->delete();

        return response()->json(['status' => 'deleted']);
    }

    /**
     * @return array<string, array<int, string>>
     */
    private function draftValidationRules(Template $template): array
    {
        $rules = [
            'template_id' => ['required', 'exists:templates,id'],
            'title' => ['nullable', 'string', 'max:150'],
            'recipient_name' => ['nullable', 'string', 'max:150'],
            'recipient_email' => ['nullable', 'email', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'date_of_issue' => ['nullable', 'date'],
            'date_of_expiry' => ['nullable', 'date'],
        ];

        foreach ($template->editableCustomFields() as $field) {
            if ($field['type'] === 'text') {
                $rules["custom_fields.{$field['key']}"] = ['nullable', 'string', 'max:500'];
            }
        }

        return $rules;
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
                $paths[$field['key']] = $file->store('certificates/custom-fields', 'local');
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

    /**
     * Bulk-issued certificates (certificate_batch_id set) stay on the
     * queue, matching how the rest of that batch was generated. A
     * single-issued certificate regenerates synchronously instead, the
     * same way it was generated in the first place - the request just
     * waits the few seconds it takes rather than polling.
     */
    public function regenerate(Request $request, Certificate $certificate, CertificateRenderService $renderService): JsonResponse
    {
        $this->authorizeAccess($request, $certificate);

        $certificate->refresh();

        if ($certificate->certificate_batch_id) {
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

        $renderService->generateAssetsSynchronously($certificate);

        $certificate->refresh();

        return response()->json([
            ...$this->statusPayload($certificate),
            'message' => $certificate->image_path ? 'Certificate regenerated.' : 'Regeneration failed - please try again.',
        ]);
    }

    public function download(Request $request, Certificate $certificate)
    {
        $this->authorizeAccess($request, $certificate);

        abort_unless($certificate->pdf_path, 404, 'The PDF is still being generated.');

        return Storage::disk('local')->download($certificate->pdf_path, "{$certificate->title}.pdf");
    }

    /**
     * Owner/admin-only equivalent of the public verify page's image route -
     * used by every authenticated <img> tag (dashboard, certificate index,
     * show page, admin index) now that certificate assets live on the
     * private disk and can no longer be linked to directly.
     */
    public function image(Request $request, Certificate $certificate): StreamedResponse
    {
        $this->authorizeAccess($request, $certificate);

        abort_unless($certificate->image_path, 404);

        return Storage::disk('local')->response($certificate->image_path);
    }

    public function qr(Request $request, Certificate $certificate): StreamedResponse
    {
        $this->authorizeAccess($request, $certificate);

        abort_unless($certificate->qr_code_path, 404);

        return Storage::disk('local')->response($certificate->qr_code_path);
    }

    private function authorizeAccess(Request $request, Certificate $certificate): void
    {
        abort_unless(
            $certificate->user_id === $request->user()->id || $request->user()->isAdmin(),
            403
        );
    }

    /**
     * Only applies to bulk-issued certificates - a single-issued one is
     * always fully done or 'failed' by the time this could ever run (see
     * IssueSingleCertificateAction), so silently re-queueing it here would
     * reintroduce a queue-worker dependency the synchronous path exists to
     * remove. Its retry path is the regenerate button, not this.
     */
    private function queueMissingGenerationJobs(Certificate $certificate): void
    {
        if (! $certificate->certificate_batch_id) {
            return;
        }

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
                ? route('certificates.image', $certificate).'?v='.$cacheBust
                : null,
            'pdf_ready' => (bool) $certificate->pdf_path,
            'image_generation_status' => $certificate->image_generation_status,
            'display_status' => $certificate->displayStatus(),
            'verification_code' => $certificate->verification_code,
            'template_name' => $certificate->template->name,
            'qr_url' => $certificate->qr_code_path
                ? route('certificates.qr', $certificate).'?v='.$cacheBust
                : null,
        ];
    }
}
