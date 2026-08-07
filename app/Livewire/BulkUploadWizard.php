<?php

namespace App\Livewire;

use App\Models\CertificateBatch;
use App\Models\Template;
use App\Models\User;
use App\Services\BulkUploadIngestService;
use App\Services\BulkUploadWizardService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;

class BulkUploadWizard extends Component
{
    use WithFileUploads;

    /** @var 'tenant'|'admin' */
    public string $mode = 'tenant';

    /** @var 'template'|'upload'|'setup'|'map'|'review' */
    public string $step = 'template';

    public mixed $templateId = null;

    public mixed $userId = null;

    public ?int $batchId = null;

    /** @var mixed */
    public $file = null;

    /** @var array<string, string|int|null> */
    public array $mapping = [
        'title' => '',
        'recipient_name' => '',
        'recipient_email' => '',
        'description' => '',
        'date_of_issue' => '',
        'date_of_expiry' => '',
    ];

    /** @var array<int, string> */
    public array $headers = [];

    public function mount(
        string $mode = 'tenant',
        ?string $step = null,
        mixed $templateId = null,
        ?int $batchId = null,
    ): void {
        abort_unless(auth()->check(), 403);

        $this->mode = $mode;
        $this->templateId = $templateId !== null && $templateId !== '' ? (int) $templateId : null;
        $this->batchId = $batchId;

        if ($batchId !== null) {
            $batch = $this->batch();
            $this->authorizeBatch($batch);
            $this->templateId = $batch->template_id;
            $this->loadMappingState($batch, app(BulkUploadIngestService::class));
            $this->step = $step ?? ($batch->status === 'queued' ? 'review' : 'map');

            return;
        }

        if ($mode === 'admin') {
            abort_unless(auth()->user()->isAdmin(), 403);
            $this->step = $step ?? 'setup';

            return;
        }

        if ($this->templateId !== null) {
            Template::active()->findOrFail($this->templateId);
            $this->step = $step ?? 'upload';

            return;
        }

        $this->step = $step ?? 'template';
    }

    public function selectTemplate(int $templateId): void
    {
        Template::active()->findOrFail($templateId);
        $this->templateId = $templateId;
        $this->step = 'upload';
        $this->resetErrorBag();
    }

    public function backToTemplate(): void
    {
        $this->step = 'template';
        $this->templateId = null;
        $this->file = null;
        $this->resetErrorBag();
    }

    /**
     * Deliberately not named `upload` - that name collides with Livewire's
     * own reserved $wire.upload() client-side file-upload function (used
     * internally by WithFileUploads/wire:model="file"). A component action
     * named exactly `upload` gets intercepted by that magic method instead
     * of being called as a normal server action, which crashes with no
     * file argument the moment wire:click="upload" fires - see the
     * "Continue to Mapping" button in bulk-upload-wizard.blade.php.
     */
    public function continueToMapping(BulkUploadWizardService $wizard): void
    {
        $this->validate([
            'templateId' => ['required', 'integer', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $batch = $wizard->createTenantBatch(auth()->user(), (int) $this->templateId, $this->file);
        $this->enterMapStep($batch, app(BulkUploadIngestService::class));
    }

    public function adminUpload(BulkUploadWizardService $wizard): void
    {
        abort_unless(auth()->user()->isAdmin(), 403);

        $this->validate([
            'userId' => ['required', 'integer', 'exists:users,id'],
            'templateId' => ['required', 'integer', 'exists:templates,id'],
            'file' => ['required', 'file', 'mimes:csv,xlsx,xls', 'max:10240'],
        ]);

        $batch = $wizard->createAdminBatch(
            auth()->user(),
            (int) $this->userId,
            (int) $this->templateId,
            $this->file,
        );
        $this->enterMapStep($batch, app(BulkUploadIngestService::class));
    }

    public function saveMapping(BulkUploadWizardService $wizard): void
    {
        $batch = $this->batch();
        $this->authorizeBatch($batch);

        $this->validate([
            'mapping' => ['required', 'array'],
            'mapping.title' => ['required', 'integer'],
            'mapping.recipient_name' => ['required', 'integer'],
            'mapping.recipient_email' => ['required', 'integer'],
            'mapping.description' => ['nullable', 'integer'],
            'mapping.date_of_issue' => ['required', 'integer'],
            'mapping.date_of_expiry' => ['nullable', 'integer'],
        ]);

        try {
            $wizard->applyMapping($batch, $this->mapping);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        $this->batchId = $batch->id;
        $this->step = 'review';
        $this->resetErrorBag();
    }

    public function backToMap(): void
    {
        $batch = $this->batch();
        $this->authorizeBatch($batch);
        $this->loadMappingState($batch, app(BulkUploadIngestService::class));
        $this->step = 'map';
        $this->resetErrorBag();
    }

    public function backFromMap(): void
    {
        if ($this->mode === 'admin') {
            $this->step = 'setup';
            $this->batchId = null;
            $this->headers = [];
            $this->file = null;
            $this->resetErrorBag();

            return;
        }

        $this->step = 'upload';
        $this->batchId = null;
        $this->headers = [];
        $this->file = null;
        $this->resetErrorBag();
    }

    public function confirm(BulkUploadWizardService $wizard)
    {
        $batch = $this->batch();
        $this->authorizeBatch($batch);

        try {
            $wizard->confirm($batch);
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->errors());

            return;
        }

        return $this->redirect(route('bulk-upload.status', $batch));
    }

    public function render(): View
    {
        $batch = $this->batchId ? CertificateBatch::with('template')->find($this->batchId) : null;

        return view('livewire.bulk-upload-wizard', [
            'templates' => $this->step === 'template' || $this->step === 'setup'
                ? Template::active()->orderBy('name')->get()
                : collect(),
            'orgUsers' => $this->mode === 'admin' && $this->step === 'setup'
                ? User::where('role', 'user')->orderBy('organization_name')->get()
                : collect(),
            'template' => $this->templateId
                ? Template::find($this->templateId)
                : $batch?->template,
            'batch' => $batch,
            'pendingCount' => $batch?->items()->where('status', 'pending')->count() ?? 0,
            'skippedCount' => $batch?->items()->where('status', 'skipped')->count() ?? 0,
            'failedCount' => $batch?->items()->where('status', 'failed')->count() ?? 0,
            'stepperCurrent' => match ($this->step) {
                'template' => 'Select Template',
                'upload', 'setup' => 'Upload Data',
                'map' => 'Map Columns',
                'review' => 'Review & Issue',
                default => 'Select Template',
            },
        ]);
    }

    private function enterMapStep(CertificateBatch $batch, BulkUploadIngestService $ingest): void
    {
        $this->batchId = $batch->id;
        $this->file = null;
        $this->loadMappingState($batch, $ingest);
        $this->step = 'map';
        $this->resetErrorBag();
    }

    private function loadMappingState(CertificateBatch $batch, BulkUploadIngestService $ingest): void
    {
        abort_unless($batch->temp_upload_path && Storage::disk('local')->exists($batch->temp_upload_path), 404);

        $this->headers = $ingest->parseHeaders(Storage::disk('local')->path($batch->temp_upload_path));
        $suggested = $batch->column_mapping ?: $ingest->guessColumnMapping($this->headers);

        foreach (array_keys($this->mapping) as $field) {
            $this->mapping[$field] = array_key_exists($field, $suggested) ? $suggested[$field] : '';
        }
    }

    private function batch(): CertificateBatch
    {
        return CertificateBatch::query()->findOrFail($this->batchId);
    }

    private function authorizeBatch(CertificateBatch $batch): void
    {
        abort_unless(
            $batch->user_id === auth()->id() || auth()->user()->isAdmin(),
            403
        );
    }
}
