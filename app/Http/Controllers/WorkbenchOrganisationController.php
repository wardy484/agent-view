<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\RenameWorkbenchRequest;
use App\Models\McpCallLog;
use App\Models\Workbench;
use App\Policies\WorkbenchPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * REQ-M6-002..005: owner-only organisational mutations on a workbench.
 * Individual actions delegate authorisation to {@see WorkbenchPolicy};
 * every successful mutation writes an audit row into `mcp_call_logs`
 * (reusing the existing log table, no new table — REQ-M6-002).
 */
class WorkbenchOrganisationController extends Controller
{
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
