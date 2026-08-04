<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactRequestController extends Controller
{
    public function index(): View
    {
        return view('admin.contact-requests.index');
    }

    public function show(ContactRequest $contactRequest): View
    {
        $contactRequest->loadMissing('user', 'handler');

        return view('admin.contact-requests.show', compact('contactRequest'));
    }

    public function updateStatus(Request $request, ContactRequest $contactRequest): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:open,in_progress,closed'],
        ]);

        $contactRequest->forceFill([
            'status' => $validated['status'],
            'handled_by' => $request->user()->id,
            'handled_at' => $validated['status'] === ContactRequest::STATUS_CLOSED ? now() : $contactRequest->handled_at,
        ])->save();

        return back()->with('status', 'Contact request updated.');
    }
}
