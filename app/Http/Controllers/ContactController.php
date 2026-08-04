<?php

namespace App\Http\Controllers;

use App\Models\ContactRequest;
use App\Notifications\AdminContactRequestNotification;
use App\Support\NotifyAdmins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function create(Request $request): View
    {
        $user = $request->user();

        return view('contact', [
            'name' => old('name', $user?->name),
            'email' => old('email', $user?->email),
            'organization' => old('organization', $user?->organization_name),
            'subject' => old('subject', $request->string('subject')->toString() ?: null),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'organization' => ['nullable', 'string', 'max:160'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $contactRequest = ContactRequest::create([
            ...$validated,
            'user_id' => $request->user()?->id,
            'status' => ContactRequest::STATUS_OPEN,
        ]);

        NotifyAdmins::notify(
            new AdminContactRequestNotification($contactRequest),
            'Failed to send admin contact-request alert.',
            ['contact_request_id' => $contactRequest->id],
        );

        return redirect()
            ->route('contact')
            ->with('status', 'Thanks — your message was sent. Our team will get back to you soon.');
    }
}
