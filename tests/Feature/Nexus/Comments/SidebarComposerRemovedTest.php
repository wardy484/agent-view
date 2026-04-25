<?php

declare(strict_types=1);

/**
 * REQ-M6-030: the "create new root comment" composer in the snapshot
 * sidebar is removed. The floating pill (REQ-M6-027) is the sole authoring
 * surface for new root comments. The sidebar still hosts the comment list,
 * reply boxes, reactions, status controls, and the historical-view banner.
 */
it('REQ-M6-030: snapshot sidebar source no longer ships the create-new composer', function (): void {
    $path = resource_path('js/components/nexus/snapshot-sidebar.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    // The sidebar's create-new composer block was identifiable by these
    // test-id strings and the CommentComposer subcomponent. They must all
    // have been excised so no caller in the live code path can mount them.
    expect($source)
        ->not->toContain('data-testid="snapshot-sidebar-composer"')
        ->not->toContain('snapshot-sidebar-composer-body')
        ->not->toContain('snapshot-sidebar-composer-proposed')
        ->not->toContain('snapshot-sidebar-composer-submit')
        // The CommentComposer subcomponent is gone.
        ->not->toContain('function CommentComposer(')
        // The sidebar's Props no longer accept composerSelection or
        // onComposerClose — the snapshot page stops threading selection
        // through the sidebar.
        ->not->toContain('composerSelection: ComposerSelection')
        ->not->toContain('onComposerClose: () => void')
        // Reply boxes, reactions, and status controls remain.
        ->toContain('snapshot-sidebar-reply-box')
        ->toContain('snapshot-sidebar-reaction-picker')
        ->toContain('snapshot-sidebar-comment-status-select')
        // The historical-view banner remains.
        ->toContain('snapshot-sidebar-historical-banner');
});

it('REQ-M6-030: report-view no longer threads composerSelection into the sidebar', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->not->toContain('composerSelection={composerSelection}')
        ->not->toContain('onComposerClose=')
        ->not->toContain('setComposerSelection');
});
