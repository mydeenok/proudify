<?php

namespace App\Livewire\Admin;

use App\Models\UserSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UserSubscriptionsTable extends Component
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

    public function render(): View
    {
        return view('livewire.admin.user-subscriptions-table', [
            'subscriptions' => $this->subscriptions(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, UserSubscription>
     */
    private function subscriptions(): LengthAwarePaginator
    {
        return UserSubscription::query()
            ->with(['user', 'subscription'])
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->whereHas('user', fn ($q) => $q
                    ->where('organization_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%"));
            })
            ->latest('start_date')
            ->paginate(15);
    }
}
