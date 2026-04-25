<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\SnapshotVersion;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * REQ-M7-002: broadcast on `snapshot.{snapshot_id}` after a new revision is
 * persisted and the parent snapshot's `current_version_id` is bumped.
 *
 * The payload is intentionally minimal — `data_payload` NEVER ships down the
 * wire (it can be megabytes and contains the whole user/agent contribution).
 * Subscribers that need the full payload re-fetch via the authenticated
 * snapshot endpoint, where the SnapshotPolicy gate is the single source of
 * truth.
 */
class SnapshotVersionAppended implements ShouldBroadcast
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public int $snapshotId;

    public int $versionId;

    public int $revision;

    public string $viewType;

    public ?string $summary;

    public function __construct(SnapshotVersion $version)
    {
        $this->snapshotId = (int) $version->snapshot_id;
        $this->versionId = (int) $version->id;
        $this->revision = (int) $version->revision;
        $this->viewType = (string) $version->view_type;

        $metadata = $version->metadata;
        $summary = is_array($metadata) ? ($metadata['summary'] ?? null) : null;
        $this->summary = is_string($summary) ? $summary : null;
    }

    /**
     * Private channel — Reverb prepends `private-` automatically. Mirrors
     * `SnapshotPolicy@view` for owner + active grantees in routes/channels.php.
     */
    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('snapshot.'.$this->snapshotId);
    }

    public function broadcastAs(): string
    {
        return 'SnapshotVersionAppended';
    }

    /**
     * REQ-M7-002: payload is `{snapshot_id, version_id, revision, view_type, summary?}`.
     * `summary` is omitted entirely when absent — never include `data_payload`.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        $payload = [
            'snapshot_id' => $this->snapshotId,
            'version_id' => $this->versionId,
            'revision' => $this->revision,
            'view_type' => $this->viewType,
        ];

        if ($this->summary !== null) {
            $payload['summary'] = $this->summary;
        }

        return $payload;
    }
}
