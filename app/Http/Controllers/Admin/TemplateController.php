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
    public function index(): View
    {
        return view('admin.templates.index');
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
            // Always blank at create — design happens in the Visual Builder.
            // An empty html_content keeps canvas_json free of background_html
            // so the Chrome-free canvas render driver can handle it.
            'html_content' => '',
            'created_by' => $request->user()->id,
        ]);

        if ($request->hasFile('thumbnail')) {
            $template->update(['thumbnail_path' => $this->storeThumbnail($request, $template)]);
        }

        // Drop straight into the Visual Builder — basic details are already
        // captured; designing (or leaving HTML blank for the Chrome-free
        // path) is the next step every admin takes after create.
        return redirect()
            ->route('admin.templates.builder', $template)
            ->with('status', "\"{$template->name}\" was created — design it below.");
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
     * Canva-style "start from a template": clone the design document
     * (canvas_json) into a new inactive row and drop the admin into the
     * builder. Skips background_html so the clone stays Chrome-free-
     * renderable even if the source was a legacy HTML import.
     */
    public function duplicate(Template $template): RedirectResponse
    {
        $canvasJson = $template->canvas_json;

        if (is_array($canvasJson)) {
            unset($canvasJson['background_html']);
        }

        $clone = Template::create([
            'name' => $template->name.' (Copy)',
            'category' => $template->category,
            'html_content' => '',
            'canvas_json' => $canvasJson,
            'custom_field_schema' => $template->custom_field_schema,
            'page_format' => $template->page_format,
            'orientation' => $template->orientation,
            'is_active' => false,
            'is_exclusive' => $template->is_exclusive,
            'created_by' => request()->user()->id,
        ]);

        return redirect()
            ->route('admin.templates.builder', $clone)
            ->with('status', "Duplicated \"{$template->name}\" — edit the copy below.");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Template $template = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:50'],
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
