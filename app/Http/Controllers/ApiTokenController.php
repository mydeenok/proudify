<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'website_name' => ['nullable', 'string', 'max:100'],
            'website_url' => ['nullable', 'url', 'max:255'],
        ]);

        $token = $request->user()->createToken($validated['name']);

        // website_name/website_url are descriptive metadata only, not an
        // enforced restriction - Sanctum's createToken() signature has no
        // room for extra columns, so they're set on the model it just
        // created rather than passed into the call itself.
        $token->accessToken->update([
            'website_name' => $validated['website_name'] ?? null,
            'website_url' => $validated['website_url'] ?? null,
        ]);

        // Sanctum only ever exposes the plaintext value on the response of
        // createToken() - it's hashed before storage, so this is the one
        // and only chance to show it. Flashed to the session (not stored)
        // so a page refresh clears it.
        return redirect()
            ->route('profile.edit')
            ->with('status', 'api-token-created')
            ->with('plainTextToken', $token->plainTextToken);
    }

    /**
     * Scoped delete through the authenticated user's own tokens() relation
     * - never a bare PersonalAccessToken::find()->delete(), which would let
     * one tenant revoke another's token by guessing an id.
     */
    public function destroy(Request $request, int $tokenId): RedirectResponse
    {
        $request->user()->tokens()->where('id', $tokenId)->delete();

        return redirect()->route('profile.edit')->with('status', 'api-token-revoked');
    }
}
