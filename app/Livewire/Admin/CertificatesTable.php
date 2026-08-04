<?php

namespace App\Livewire\Admin;

use App\Models\Certificate;
use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CertificatesTable extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $template_id = '';

    #[Url(history: true)]
    public string $period = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingTemplateId(): void
    {
        $this->resetPage();
    }

    public function updatingPeriod(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.admin.certificates-table', [
            'certificates' => $this->certificates(),
            'templates' => $this->templates(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Certificate>
     */
    private function certificates(): LengthAwarePaginator
    {
        return Certificate::query()
            ->with(['user', 'template'])
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%")
                        ->orWhere('verification_code', 'like', "%{$search}%")
                        ->orWhereHas('user', fn ($q) => $q->where('organization_name', 'like', "%{$search}%"));
                });
            })
            ->when($this->status !== '', function ($query) {
                match ($this->status) {
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
            ->when($this->template_id !== '', fn ($query) => $query->where('template_id', (int) $this->template_id))
            ->when($this->period === '30', fn ($query) => $query->where('created_at', '>=', now()->subDays(30)))
            ->when($this->period === '7', fn ($query) => $query->where('created_at', '>=', now()->subDays(7)))
            ->latest()
            ->paginate(20);
    }

    /**
     * @return Collection<int, Template>
     */
    private function templates(): Collection
    {
        return Template::orderBy('name')->get(['id', 'name']);
    }
}
