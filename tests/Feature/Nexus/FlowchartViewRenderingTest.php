<?php

declare(strict_types=1);

use App\Models\Snapshot;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('REQ-M2-005: snapshot page hands the flowchart data_payload to the FlowchartView', function (): void {
    $workbench = Workbench::factory()->create(['slug' => 'arch-gamma']);
    $snapshot = Snapshot::factory()->for($workbench)->create(['slug' => 'system-map']);

    $payload = ['mermaid_source' => "flowchart TD\nA --> B --> C"];

    $version = SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: 'flowchart',
        dataPayload: $payload,
    );

    $snapshot->forceFill(['current_version_id' => $version->id])->save();

    $this->actingAs($workbench->owner)
        ->withoutVite()
        ->get(route('workbench.snapshot.show', [
            'workbench' => $workbench->slug,
            'snapshot' => $snapshot->slug,
        ]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('snapshot')
            ->where('version.view_type', 'flowchart')
            ->where('version.data_payload.mermaid_source', $payload['mermaid_source'])
        );
});

it('REQ-M2-005: snapshot page dispatches flowchart view_type to the FlowchartView component', function (): void {
    $dispatcher = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($dispatcher)
        ->toContain("'flowchart'")
        ->toContain('<FlowchartView')
        ->toContain("from '@/components/nexus/flowchart-view'");
});

it('REQ-M2-005: FlowchartView renders mermaid_source via Mermaid.js in the browser', function (): void {
    $source = file_get_contents(resource_path('js/components/nexus/flowchart-view.tsx'));

    // Must lazily import mermaid and invoke mermaid.render() client-side.
    expect($source)
        ->toContain("await import('mermaid')")
        ->toContain('mermaid.render(')
        ->toContain('mermaid_source')
        ->not->toContain('router.visit')
        ->not->toContain('router.get')
        ->not->toContain('useHttp');
});
