<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    /**
     * REQ-M1-013: list the user's Sanctum personal access tokens.
     */
    public function edit(Request $request): Response
    {
        $tokens = $request->user()->tokens()
            ->orderByDesc('created_at')
            ->get(['id', 'name', 'abilities', 'last_used_at', 'created_at'])
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities ?? [],
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->values()
            ->all();

        return Inertia::render('settings/tokens', [
            'tokens' => $tokens,
            'plainTextToken' => $request->session()->get('plainTextToken'),
        ]);
    }

    /**
     * REQ-M1-013: mint a new Sanctum personal access token scoped to a
     * single workbench. The plain-text value is flashed once.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'workbench_slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
        ], [
            'workbench_slug.regex' => 'The workbench slug must be lowercase letters, numbers, and dashes.',
        ]);

        $newToken = $request->user()->createToken(
            $validated['name'],
            ['workbench:'.$validated['workbench_slug']],
        );

        return to_route('tokens.edit')->with('plainTextToken', $newToken->plainTextToken);
    }

    /**
     * REQ-M1-013: revoke one of the current user's tokens.
     */
    public function destroy(Request $request, PersonalAccessToken $token): RedirectResponse
    {
        if ((int) $token->tokenable_id !== (int) $request->user()->id
            || $token->tokenable_type !== $request->user()::class) {
            abort(404);
        }

        $token->delete();

        return to_route('tokens.edit');
    }
}
