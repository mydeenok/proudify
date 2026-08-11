<?php

namespace App\Livewire\Admin;

use App\Models\CertificateOrder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CertificateOrdersTable extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    /** @var 'all'|'pending'|'paid'|'failed'|'refunded' */
    #[Url(history: true)]
    public string $status = 'all';

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

    public function render(): View
    {
        return view('livewire.admin.certificate-orders-table', [
            'orders' => $this->orders(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, CertificateOrder>
     */
    private function orders(): LengthAwarePaginator
    {
        return CertificateOrder::query()
            ->with(['user', 'template'])
            // A paid order whose certificate never got attached is exactly
            // the "issuance failed after payment" case a refund exists
            // for - surfaced first since it's the one that needs a human.
            ->when($this->status === 'all', fn ($query) => $query->orderByRaw(
                "(status = 'paid' AND certificate_id IS NULL AND type = 'single') desc"
            ))
            ->when($this->status !== 'all', fn ($query) => $query->where('status', $this->status))
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->whereHas('user', fn ($q) => $q
                    ->where('organization_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15);
    }
}
