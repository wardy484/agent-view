<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use App\Mcp\Support\McpCallLogger;
use App\Models\FollowUpContext;
use App\Models\Workbench;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Tool;

/**
 * REQ-M3-003: returns unconsumed `follow_up_contexts` rows for a workbench and
 * marks them `consumed_at`.
 *
 * REQ-M3-005: the operation is idempotent — a consumed row is never returned
 * twice. We wrap the fetch + update in a single transaction and SELECT … FOR
 * UPDATE to prevent two concurrent calls from both returning the same row.
 */
#[Name('get_follow_up_context')]
#[Title('Get Follow Up Context')]
#[Description('Return unconsumed follow_up_contexts rows for a workbench and mark them consumed.')]
class GetFollowUpContext extends Tool
{
    public function handle(Request $request): ResponseFactory|Response
    {
        return McpCallLogger::record('get_follow_up_context', $request, fn () => $this->execute($request));
    }

    private function execute(Request $request): ResponseFactory|Response
    {
        $slug = (string) $request->get('workbench_slug');

        $workbench = Workbench::query()->where('slug', $slug)->first();

        if ($workbench === null) {
            return Response::make([Response::text('[]')])->withStructuredContent([
                'workbench_slug' => $slug,
                'contexts' => [],
            ]);
        }

        $contexts = DB::transaction(function () use ($workbench): array {
            /** @var list<FollowUpContext> $rows */
            $rows = FollowUpContext::query()
                ->where('workbench_id', $workbench->id)
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->orderBy('id')
                ->get()
                ->all();

            if ($rows === []) {
                return [];
            }

            $ids = array_map(static fn (FollowUpContext $row): int => (int) $row->id, $rows);

            FollowUpContext::query()
                ->whereIn('id', $ids)
                ->update(['consumed_at' => now()]);

            return array_map(static fn (FollowUpContext $row): array => [
                'id' => (int) $row->id,
                'snapshot_id' => $row->snapshot_id,
                'payload' => $row->payload,
                'created_at' => $row->created_at?->toIso8601String(),
            ], $rows);
        });

        $payloadsJson = json_encode($contexts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return Response::make([
            Response::text($payloadsJson === false ? '[]' : $payloadsJson),
        ])->withStructuredContent([
            'workbench_slug' => $workbench->slug,
            'contexts' => $contexts,
        ]);
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'workbench_slug' => $schema->string()
                ->description('Slug of the workbench whose follow-up contexts should be consumed.')
                ->required(),
        ];
    }
}
