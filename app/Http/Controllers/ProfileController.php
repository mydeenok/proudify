<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Logos and the signature are consumed directly by IssueSingleCertificateAction
     * when it stamps a certificate, so this is functional configuration, not just
     * display preference. Cap of 5 logos is an app-layer sanity limit — there is
     * no product requirement for more than that on a single certificate footer.
     */
    public function updateOrganization(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'org_logos' => ['nullable', 'array'],
            'org_logos.*' => ['image', 'max:2048'],
            'signature' => ['nullable', 'image', 'max:1024'],
            'remove_logos' => ['nullable', 'array'],
            'remove_logos.*' => ['string'],
        ]);

        $user = $request->user();
        $logos = $user->org_logos ?? [];

        foreach ($request->input('remove_logos', []) as $pathToRemove) {
            if (($index = array_search($pathToRemove, $logos, true)) !== false) {
                Storage::disk('local')->delete($pathToRemove);
                unset($logos[$index]);
            }
        }
        $logos = array_values($logos);

        if ($request->hasFile('org_logos')) {
            foreach ($request->file('org_logos') as $file) {
                if (count($logos) >= 5) {
                    break;
                }
                $logos[] = $file->store('organization-logos', 'local');
            }
        }

        $user->org_logos = $logos;

        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                Storage::disk('local')->delete($user->signature_path);
            }
            $user->signature_path = $request->file('signature')->store('signatures', 'local');
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'organization-updated');
    }

    /**
     * Always the current user's own logo by array index - there is no
     * cross-account lookup here, so no ownership check beyond "is
     * authenticated" is needed.
     */
    public function logo(Request $request, int $index): StreamedResponse
    {
        $path = ($request->user()->org_logos ?? [])[$index] ?? null;

        abort_unless($path, 404);

        return Storage::disk('local')->response($path);
    }

    public function signature(Request $request): StreamedResponse
    {
        abort_unless($request->user()->signature_path, 404);

        return Storage::disk('local')->response($request->user()->signature_path);
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
