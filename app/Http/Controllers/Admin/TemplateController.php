<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = Template::query()
            ->when($request->string('search')->isNotEmpty(), fn ($query) => $query->where('name', 'like', '%'.$request->string('search').'%'))
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('admin.templates.index', ['templates' => $templates]);
    }

    public function create(): View
    {
        return view('admin.templates.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $template = Template::create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('thumbnail')) {
            $template->update(['thumbnail_path' => $this->storeThumbnail($request, $template)]);
        }

        return redirect()->route('admin.templates.index')->with('status', "\"{$template->name}\" was created.");
    }

    public function edit(Template $template): View
    {
        return view('admin.templates.edit', ['template' => $template]);
    }

    public function update(Request $request, Template $template): RedirectResponse
    {
        $validated = $this->validated($request, $template);

        $template->update($validated);

        if ($request->hasFile('thumbnail')) {
            $template->update(['thumbnail_path' => $this->storeThumbnail($request, $template)]);
        }

        return redirect()->route('admin.templates.index')->with('status', "\"{$template->name}\" was updated.");
    }

    public function destroy(Template $template): RedirectResponse
    {
        $template->delete();

        return back()->with('status', "\"{$template->name}\" was deleted.");
    }

    public function toggleStatus(Template $template): RedirectResponse
    {
        $template->update(['is_active' => ! $template->is_active]);

        $state = $template->is_active ? 'live' : 'draft';

        return back()->with('status', "\"{$template->name}\" is now {$state}.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Template $template = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
            'html_content' => ['required', 'string'],
            'page_format' => ['required', 'string', 'in:a4,letter'],
            'orientation' => ['required', 'string', 'in:landscape,portrait'],
            'is_active' => ['sometimes', 'boolean'],
            'is_exclusive' => ['sometimes', 'boolean'],
            'thumbnail' => ['nullable', 'image', 'max:2048'],
        ]);
    }

    private function storeThumbnail(Request $request, Template $template): string
    {
        return $request->file('thumbnail')->store("templates/{$template->id}", 'public');
    }
}
