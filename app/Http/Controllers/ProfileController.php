<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

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
                Storage::disk('public')->delete($pathToRemove);
                unset($logos[$index]);
            }
        }
        $logos = array_values($logos);

        if ($request->hasFile('org_logos')) {
            foreach ($request->file('org_logos') as $file) {
                if (count($logos) >= 5) {
                    break;
                }
                $logos[] = $file->store('organization-logos', 'public');
            }
        }

        $user->org_logos = $logos;

        if ($request->hasFile('signature')) {
            if ($user->signature_path) {
                Storage::disk('public')->delete($user->signature_path);
            }
            $user->signature_path = $request->file('signature')->store('signatures', 'public');
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'organization-updated');
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
