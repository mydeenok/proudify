<?php

namespace App\Livewire\Admin;

use App\Models\PersonalAccessToken;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ApiTokensTable extends Component
{
    use WithPagination;

    #[Url(history: true)]
    public string $search = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function revoke(int $tokenId): void
    {
        PersonalAccessToken::whereKey($tokenId)->delete();
    }

    public function render(): View
    {
        return view('livewire.admin.api-tokens-table', [
            'tokens' => $this->tokens(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, PersonalAccessToken>
     */
    private function tokens(): LengthAwarePaginator
    {
        return PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->with(['tokenable', 'requestLogs' => fn ($query) => $query->latest('created_at')->limit(1)])
            ->withCount('requestLogs')
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where('name', 'like', "%{$search}%")
                    ->orWhereHas('tokenable', fn ($q) => $q
                        ->where('organization_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(15);
    }
}
