<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * REQ-M4-001: per-snapshot sharing visibility.
 *
 * - {@see self::Private}: default; only the workbench owner can see the snapshot.
 * - {@see self::Link}: anyone with the unguessable `share_token` (REQ-M4-002) can view.
 * - {@see self::Shared}: explicit allowlist via `snapshot_shares` (REQ-M4-003).
 *
 * A viewer (non-owner) always resolves to the snapshot's current revision
 * via `current_version_id`; historical revisions remain hidden.
 */
enum SnapshotVisibility: string
{
    case Private = 'private';
    case Link = 'link';
    case Shared = 'shared';
}
