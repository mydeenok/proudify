<?php

namespace App\Livewire\Admin;

use App\Models\ContactRequest;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ContactRequestsTable extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

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
        return view('livewire.admin.contact-requests-table', [
            'contactRequests' => $this->contactRequests(),
            'openCount' => ContactRequest::where('status', ContactRequest::STATUS_OPEN)->count(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, ContactRequest>
     */
    private function contactRequests(): LengthAwarePaginator
    {
        return ContactRequest::query()
            ->with('user')
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('organization', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%");
                });
            })
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->latest()
            ->paginate(20);
    }
}
