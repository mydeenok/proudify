<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplateController extends Controller
{
    public function index(Request $request): View
    {
        $templates = Template::active()
            ->when($request->string('category')->isNotEmpty(), fn ($query) => $query->where('category', $request->string('category')))
            ->orderBy('name')
            ->get();

        $categories = Template::active()->whereNotNull('category')->distinct()->pluck('category');

        return view('templates.index', ['templates' => $templates, 'categories' => $categories]);
    }
}
