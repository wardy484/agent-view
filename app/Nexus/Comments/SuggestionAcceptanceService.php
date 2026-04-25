<?php

declare(strict_types=1);

namespace App\Nexus\Comments;

use App\Enums\CommentKind;
use App\Enums\CommentResolution;
use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\SnapshotVersion;
use App\Models\User;
use App\Nexus\SnapshotVersioning;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * REQ-M6-008: owner-only acceptance of a `kind = suggestion` comment.
 *
 * Resolves the comment's hybrid anchor against the snapshot's *current*
 * revision. On a successful resolution it patches the resolved range in the
 * relevant block's `body` with the suggestion's `proposed_text`, calls
 * {@see SnapshotVersioning::append()} to mint a new report revision, and
 * atomically transitions the comment to `status = resolved, resolution = user,
 * addressed_on_version_id = <new revision>` — all inside a single DB
 * transaction so a failure anywhere unwinds the whole operation.
 *
 * Stale anchors throw {@see StaleAnchorException}; the controller maps that
 * to HTTP 409. Non-suggestion comments throw {@see InvalidArgumentException};
 * the policy gate catches owner mismatch before this service is reached.
 */
final class SuggestionAcceptanceService
{
    public function accept(Comment $comment, User $acceptingUser): SnapshotVersion
    {
        if ($comment->kind !== CommentKind::Suggestion) {
            throw new InvalidArgumentException(
                'SuggestionAcceptanceService::accept() requires a comment of kind=suggestion.',
            );
        }

        return DB::transaction(function () use ($comment, $acceptingUser): SnapshotVersion {
            $snapshot = $comment->snapshot()->lockForUpdate()->firstOrFail();
            $current = $snapshot->currentVersion()->firstOrFail();

            $resolved = AnchorResolver::resolve($comment, $current);

            if ($resolved->status !== 'open') {
                throw new StaleAnchorException($resolved->reason ?? 'unknown');
            }

            $newPayload = $this->patchPayload(
                $current->data_payload,
                $resolved->blockId,
                (int) $resolved->start,
                (int) $resolved->end,
                (string) $comment->proposed_text,
            );

            $newVersion = SnapshotVersioning::append(
                snapshot: $snapshot,
                viewType: 'report',
                dataPayload: $newPayload,
                metadata: [
                    'author_kind' => 'user',
                    'created_by_user_id' => $acceptingUser->id,
                    'reason' => 'accepted_suggestion',
                    'comment_id' => $comment->id,
                ],
            );

            $comment->forceFill([
                'status' => CommentStatus::Resolved,
                'resolution' => CommentResolution::User,
                'addressed_on_version_id' => $newVersion->id,
            ])->save();

            return $newVersion;
        });
    }

    /**
     * Replace `[start, end)` of the matched block's `body` with
     * `$proposedText`, leaving every other field of every other block alone
     * so that ReportViewSchema's id-carry-forward pass keeps anchors stable.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function patchPayload(array $payload, string $blockId, int $start, int $end, string $proposedText): array
    {
        $blocks = $payload['blocks'] ?? [];
        if (! is_array($blocks)) {
            return $payload;
        }

        foreach ($blocks as $index => $block) {
            if (! is_array($block)) {
                continue;
            }
            if (($block['id'] ?? null) !== $blockId) {
                continue;
            }

            $body = (string) ($block['body'] ?? '');
            $patched = substr($body, 0, $start).$proposedText.substr($body, $end);
            $blocks[$index]['body'] = $patched;
            break;
        }

        $payload['blocks'] = array_values($blocks);

        return $payload;
    }
}
