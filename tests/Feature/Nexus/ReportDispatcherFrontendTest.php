<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

/**
 * REQ-M5-008 is primarily a client-side concern — the React dispatcher
 * in resources/js/pages/snapshot.tsx routes `view_type === "report"` to
 * the <ReportView /> component, which walks resolved_blocks and delegates
 * embed blocks to the existing TableView / KanbanView / FlowchartView /
 * SlideDeckView components.
 *
 * The following tests are the PHP-side guards that the client dispatcher
 * is wired up:
 *   1. The report-view.tsx component file exists with the expected exports.
 *   2. The snapshot.tsx dispatcher imports and references ReportView so the
 *      report arm is reachable.
 *   3. The Inertia page payload carries everything the React renderer needs
 *      (view_type + resolved_blocks + revision badge data).
 */
it('REQ-M5-008: report view React component ships with expected exports', function (): void {
    $path = resource_path('js/components/nexus/report-view.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function ReportView')
        ->toContain('export type ReportViewPayload')
        ->toContain('data-testid="nexus-report-view"')
        ->toContain("view_type === 'table'")
        ->toContain("view_type === 'kanban'")
        ->toContain("view_type === 'flowchart'")
        ->toContain("view_type === 'slide_deck'")
        // Revision badge: "v{pinned} · current v{latest}"
        ->toContain('pinned_revision')
        ->toContain('current_revision')
        // Selection-is-disabled-in-report-mode marker (REQ-M5-008).
        ->toContain('data-report-embed-readonly="true"');
});

it('REQ-M5-008: report embed "Open" link uses the Wayfinder snapshot route (not a hand-built /workbench/... URL that 404s)', function (): void {
    $source = (string) file_get_contents(resource_path('js/components/nexus/report-view.tsx'));

    // The hand-rolled singular "/workbench/${slug}/${slug}" URL does not
    // match routes/web.php (which is "/workbenches/{wb}/snapshots/{snap}"),
    // so clicking the embed "Open" button 404s. Guard against regression by
    // requiring the Wayfinder-generated route instead.
    expect($source)
        ->toContain("from '@/routes/workbench/snapshot'")
        ->toContain('snapshotShow.url(')
        ->not->toContain('`/workbench/${');

    // Confirm the underlying Wayfinder route still points at the plural
    // `/workbenches/...` path so the import above resolves to a real URL.
    $routeSource = (string) file_get_contents(resource_path('js/routes/workbench/snapshot/index.ts'));
    expect($routeSource)->toContain('/workbenches/{workbench}/snapshots/{snapshot}');
});

it('REQ-M5-008: snapshot.tsx dispatcher routes report view_type to ReportView', function (): void {
    $source = (string) file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($source)
        ->toContain("from '@/components/nexus/report-view'")
        ->toContain("version.view_type === 'report'")
        ->toContain('<ReportView');
});

it('REQ-M5-008: Inertia payload for a report snapshot carries everything the dispatcher needs', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create(['owner_user_id' => $owner->id, 'slug' => 'm5-wb']);

    $table = Snapshot::factory()->for($workbench)->create(['slug' => 'tbl', 'title' => 'KPIs']);
    SnapshotVersioning::append(
        $table,
        'table',
        ['columns' => [['key' => 'a', 'label' => 'A']], 'rows' => [['a' => '1']]],
    );

    $report = Snapshot::factory()->for($workbench)->create(['slug' => 'rpt']);
    SnapshotVersioning::append(
        $report,
        'report',
        ['blocks' => [
            ['type' => 'markdown', 'body' => '## Summary'],
            ['type' => 'embed', 'snapshot_id' => $table->id],
        ]],
    );

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $report->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'report')
            ->has('version.resolved_blocks', 2)
            ->where('version.resolved_blocks.0.type', 'markdown')
            ->where('version.resolved_blocks.1.type', 'embed')
            ->where('version.resolved_blocks.1.view_type', 'table')
            ->has('version.resolved_blocks.1.pinned_revision')
            ->has('version.resolved_blocks.1.current_revision')
            ->has('version.resolved_blocks.1.data_payload'));
});
