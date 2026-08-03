<?php

namespace App\Livewire;

use App\Models\Certificate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CertificatesIndex extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.certificates-index', [
            'certificates' => $this->certificates(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Certificate>
     */
    private function certificates(): LengthAwarePaginator
    {
        return auth()->user()
            ->certificates()
            ->with('template')
            ->latest()
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('recipient_name', 'like', "%{$search}%")
                        ->orWhere('recipient_email', 'like', "%{$search}%");
                });
            })
            ->when($this->status !== '', function ($query) {
                match ($this->status) {
                    'active' => $query->where('status', '!=', 'revoked')
                        ->where(fn ($q) => $q->whereNull('date_of_expiry')->orWhere('date_of_expiry', '>=', now()->toDateString())),
                    'expired' => $query->where('status', '!=', 'revoked')
                        ->whereNotNull('date_of_expiry')
                        ->where('date_of_expiry', '<', now()->toDateString()),
                    'revoked' => $query->where('status', 'revoked'),
                    default => null,
                };
            })
            ->paginate(10);
    }
}
