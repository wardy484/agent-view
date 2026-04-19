<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\FollowUpContext;
use App\Models\Workbench;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * REQ-M3-004: "Send back to Agent" — selecting rows/nodes in the workbench UI
 * and submitting this controller creates a `follow_up_contexts` row that the
 * agent can later consume via the `get_follow_up_context` MCP tool.
 */
class FollowUpController extends Controller
{
    public function store(Request $request, Workbench $workbench): RedirectResponse
    {
        $validated = $request->validate([
            'snapshot_id' => ['nullable', 'integer', 'exists:snapshots,id'],
            'selection' => ['required', 'array'],
        ]);

        FollowUpContext::query()->create([
            'workbench_id' => $workbench->id,
            'snapshot_id' => $validated['snapshot_id'] ?? null,
            'payload' => ['selection' => $validated['selection']],
        ]);

        return back();
    }
}
