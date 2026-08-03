<?php

namespace App\Livewire;

use App\Models\CertificateBatch;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class BulkUploadHistory extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $user_id = '';

    public function mount(): void
    {
        abort_unless(auth()->check(), 403);
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function updatingUserId(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        $viewer = auth()->user();
        $isAdmin = $viewer->isAdmin();

        return view('livewire.bulk-upload-history', [
            'batches' => $this->batches($viewer, $isAdmin),
            'orgUsers' => $isAdmin ? $this->orgUsers() : null,
            'isAdmin' => $isAdmin,
            'newUploadUrl' => $isAdmin
                ? route('admin.bulk-upload.create')
                : route('bulk-upload.select-template'),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, CertificateBatch>
     */
    private function batches(User $viewer, bool $isAdmin): LengthAwarePaginator
    {
        return CertificateBatch::query()
            ->with(['template', 'user'])
            ->when(! $isAdmin, fn ($query) => $query->where('user_id', $viewer->id))
            ->when(
                $isAdmin && $this->user_id !== '',
                fn ($query) => $query->where('user_id', (int) $this->user_id)
            )
            ->when(
                $this->status !== '',
                fn ($query) => $query->where('status', $this->status)
            )
            ->latest()
            ->paginate(20);
    }

    /**
     * @return Collection<int, User>
     */
    private function orgUsers(): Collection
    {
        return User::where('role', 'user')
            ->orderBy('organization_name')
            ->get(['id', 'organization_name']);
    }
}
