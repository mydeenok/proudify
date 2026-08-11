<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Support\UserAgentParser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
            'apiTokens' => $request->user()->tokens()->latest()->get(),
            'activeSessions' => $this->activeSessions($request),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function activeSessions(Request $request): Collection
    {
        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'id' => $session->id,
                'is_current' => $session->id === $request->session()->getId(),
                'device' => UserAgentParser::label($session->user_agent),
                'ip_address' => $session->ip_address,
                'last_active' => Carbon::createFromTimestamp($session->last_activity),
            ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        try {
            $request->user()->fill($request->validated())->save();
        } catch (Throwable $exception) {
            Log::error('Failed to save profile information.', [
                'user_id' => $request->user()->id,
                'exception' => $exception->getMessage(),
            ]);

            return Redirect::route('profile.edit')->with('status', 'profile-update-failed');
        }

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

        try {
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
        } catch (Throwable $exception) {
            Log::error('Failed to save organization branding.', [
                'user_id' => $user->id,
                'exception' => $exception->getMessage(),
            ]);

            return Redirect::route('profile.edit')->with('status', 'organization-update-failed');
        }

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
