<?php

namespace App\Livewire\Admin;

use App\Models\Subscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

class SubscriptionPlansTable extends Component
{
    #[Url(history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $type = '';

    public function mount(): void
    {
        abort_unless(auth()->user()?->isAdmin(), 403);
    }

    public function render(): View
    {
        return view('livewire.admin.subscription-plans-table', [
            'plans' => $this->plans(),
        ]);
    }

    /**
     * @return Collection<int, Subscription>
     */
    private function plans(): Collection
    {
        return Subscription::query()
            ->when($this->search !== '', function ($query) {
                $search = $this->search;
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            })
            ->when($this->status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($this->status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($this->type === 'free', function ($query) {
                $query->where('cost_month_inr', 0)->where('cost_month_usd', 0);
            })
            ->when($this->type === 'paid', function ($query) {
                $query->where(function ($query) {
                    $query->where('cost_month_inr', '>', 0)
                        ->orWhere('cost_month_usd', '>', 0);
                });
            })
            ->orderByDesc('is_default_free_plan')
            ->orderBy('sort_order')
            ->get();
    }
}
