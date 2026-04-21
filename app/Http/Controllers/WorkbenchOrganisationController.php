<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RenameWorkbenchRequest;
use App\Models\McpCallLog;
use App\Models\Workbench;
use App\Policies\WorkbenchPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * REQ-M6-002..005: owner-only organisational mutations on a workbench.
 * Individual actions delegate authorisation to {@see WorkbenchPolicy};
 * every successful mutation writes an audit row into `mcp_call_logs`
 * (reusing the existing log table, no new table — REQ-M6-002).
 */
class WorkbenchOrganisationController extends Controller
{
    /**
     * REQ-M6-003: per-user hard cap on pinned workbenches.
     */
    private const int MAX_PINS_PER_USER = 12;

    public function __construct(private readonly WorkbenchPolicy $policy) {}

    /**
     * REQ-M6-002: PATCH /workbenches/{workbench:slug} updates `name`.
     * `slug` is immutable (never written here) so agents and MCP tokens
     * continue to address the workbench by its original slug forever.
     */
    public function rename(RenameWorkbenchRequest $request, Workbench $workbench): JsonResponse
    {
        $this->authorizeOrganisation('rename', $workbench);

        $name = $request->string('name')->toString();

        DB::transaction(function () use ($workbench, $name, $request): void {
            $workbench->forceFill(['name' => $name])->save();

            McpCallLog::query()->create([
                'tool_name' => 'workbench.rename',
                'duration_ms' => 0,
                'status' => 'ok',
                'payload_bytes' => strlen($name),
                'user_id' => $request->user()?->id,
                'workbench_id' => $workbench->id,
            ]);
        });

        return response()->json([
            'workbench' => [
                'slug' => $workbench->slug,
                'name' => $workbench->name,
            ],
        ]);
    }

    /**
     * REQ-M6-003: POST /workbenches/{slug}/pin sets `pinned_at = now()`.
     * Already-pinned workbenches are idempotent — re-pinning does not
     * consume another slot against {@see self::MAX_PINS_PER_USER}.
     * Pinning an archived workbench also clears `archived_at` because
     * pin and archive are mutually exclusive states (REQ-M6-003).
     */
    public function pin(Request $request, Workbench $workbench): JsonResponse
    {
        $this->authorizeOrganisation('pin', $workbench);

        $ownerId = (int) $workbench->owner_user_id;

        DB::transaction(function () use ($workbench, $ownerId): void {
            if ($workbench->pinned_at === null) {
                $currentPins = Workbench::query()
                    ->where('owner_user_id', $ownerId)
                    ->whereNotNull('pinned_at')
                    ->count();

                if ($currentPins >= self::MAX_PINS_PER_USER) {
                    throw ValidationException::withMessages([
                        'pinned_at' => ['You can pin at most '.self::MAX_PINS_PER_USER.' workbenches.'],
                    ])->status(422);
                }
            }

            $workbench->forceFill([
                'pinned_at' => now(),
                'archived_at' => null,
            ])->save();
        });

        return response()->json([
            'workbench' => [
                'slug' => $workbench->slug,
                'pinned_at' => $workbench->pinned_at?->toIso8601String(),
                'archived_at' => null,
            ],
        ]);
    }

    /**
     * REQ-M6-004: POST /workbenches/{slug}/archive sets `archived_at = now()`
     * and clears `pinned_at` (pin and archive are mutually exclusive).
     */
    public function archive(Request $request, Workbench $workbench): JsonResponse
    {
        $this->authorizeOrganisation('archive', $workbench);

        $workbench->forceFill([
            'archived_at' => now(),
            'pinned_at' => null,
        ])->save();

        return response()->json([
            'workbench' => [
                'slug' => $workbench->slug,
                'archived_at' => $workbench->archived_at?->toIso8601String(),
                'pinned_at' => null,
            ],
        ]);
    }

    /**
     * REQ-M6-004: DELETE /workbenches/{slug}/archive clears `archived_at`.
     */
    public function unarchive(Request $request, Workbench $workbench): JsonResponse
    {
        $this->authorizeOrganisation('unarchive', $workbench);

        $workbench->forceFill(['archived_at' => null])->save();

        return response()->json([
            'workbench' => [
                'slug' => $workbench->slug,
                'archived_at' => null,
            ],
        ]);
    }

    /**
     * REQ-M6-003: DELETE /workbenches/{slug}/pin clears `pinned_at`.
     */
    public function unpin(Request $request, Workbench $workbench): JsonResponse
    {
        $this->authorizeOrganisation('unpin', $workbench);

        $workbench->forceFill(['pinned_at' => null])->save();

        return response()->json([
            'workbench' => [
                'slug' => $workbench->slug,
                'pinned_at' => null,
            ],
        ]);
    }

    /**
     * Shared gate for every REQ-M6-002..005 endpoint. Throws 403 when the
     * caller fails the ability, matching the spec's "403 for non-owners"
     * contract. Route-model-binding already returns 404 for unknown slugs.
     */
    private function authorizeOrganisation(string $ability, Workbench $workbench): void
    {
        $user = auth()->user();

        if (! $this->policy->{$ability}($user, $workbench)) {
            throw new AccessDeniedHttpException(
                "Not authorised to {$ability} this workbench."
            );
        }
    }
}
