<?php

namespace App\Livewire\Admin;

use App\Models\Template;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class TemplatesTable extends Component
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
        return view('livewire.admin.templates-table', [
            'templates' => $this->templates(),
        ]);
    }

    /**
     * @return LengthAwarePaginator<int, Template>
     */
    private function templates(): LengthAwarePaginator
    {
        return Template::query()
            ->when($this->search !== '', fn ($query) => $query->where('name', 'like', '%'.$this->search.'%'))
            ->latest()
            ->paginate(12);
    }
}
