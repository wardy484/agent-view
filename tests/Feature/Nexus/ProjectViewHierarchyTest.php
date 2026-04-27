<?php

declare(strict_types=1);

use App\Enums\CommentStatus;
use App\Enums\SnapshotVisibility;
use App\Models\Comment;
use App\Models\Snapshot;
use App\Models\User;
use App\Models\Workbench;
use App\Nexus\SnapshotVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

function m11AppendVersion(Snapshot $snapshot, string $viewType = 'table', ?string $createdAt = null): void
{
    $payload = $viewType === 'report'
        ? ['blocks' => [['type' => 'markdown', 'body' => 'Review me.']]]
        : ['columns' => [['key' => 'id']], 'rows' => [['id' => 1]]];

    SnapshotVersioning::append(
        snapshot: $snapshot,
        viewType: $viewType,
        dataPayload: $payload,
    );

    if ($createdAt !== null) {
        DB::table('snapshot_versions')
            ->where('id', $snapshot->fresh()->current_version_id)
            ->update(['created_at' => $createdAt]);
    }
}

it('REQ-M11-001: dashboard workbench rows navigate to the project detail route', function (): void {
    $src = file_get_contents(resource_path('js/pages/dashboard.tsx'));

    expect($src)
        ->toContain("show as showWorkbench")
        ->toContain('showWorkbench({ workbench: w.slug }).url')
        ->not->toContain('agentActivity({ workbench: w.slug }).url');
});

it('REQ-M11-002: project detail route lists child views by latest activity with sharing and open comment counts', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'nexus-ui',
        'name' => 'Nexus UI',
    ]);

    $older = Snapshot::factory()->for($workbench)->create([
        'slug' => 'older-view',
        'title' => 'Older View',
    ]);
    m11AppendVersion($older, 'table', now()->subDays(2)->toDateTimeString());

    $newer = Snapshot::factory()->for($workbench)->create([
        'slug' => 'newer-report',
        'title' => 'Newer Report',
    ]);
    m11AppendVersion($newer, 'report', now()->subHour()->toDateTimeString());
    $newer->setVisibility(SnapshotVisibility::Link);
    $newer->shares()->create([
        'email' => 'reviewer@example.com',
        'granted_by_user_id' => $owner->id,
    ]);

    Comment::factory()->for($newer)->create([
        'status' => CommentStatus::Open->value,
    ]);
    Comment::factory()->for($newer)->create([
        'status' => CommentStatus::Resolved->value,
    ]);

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.show', ['workbench' => $workbench->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('workbench')
            ->where('workbench.slug', 'nexus-ui')
            ->has('views', 2)
            ->where('views.0.slug', 'newer-report')
            ->where('views.0.view_type', 'report')
            ->where('views.0.current_revision', 1)
            ->where('views.0.visibility', 'link')
            ->where('views.0.active_share_count', 1)
            ->where('views.0.has_link_share', true)
            ->where('views.0.open_comment_count', 1)
            ->where('views.1.slug', 'older-view')
            ->etc()
        );

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->withoutVite()
        ->get(route('workbench.show', ['workbench' => $workbench->slug]))
        ->assertForbidden();
});

it('REQ-M11-003: project detail page uses existing workbench context and an Agent Activity empty state', function (): void {
    $owner = User::factory()->create();
    $workbench = Workbench::factory()->create([
        'owner_user_id' => $owner->id,
        'slug' => 'empty-project',
        'name' => 'Empty Project',
    ]);

    $src = file_get_contents(resource_path('js/pages/workbench.tsx'));

    expect($src)
        ->toContain('Open Agent Activity')
        ->toContain('No Views Yet')
        ->not->toContain('Create View')
        ->not->toContain('repo_');

    $this->actingAs($owner)
        ->withoutVite()
        ->get(route('workbench.show', ['workbench' => $workbench->slug]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('workbench')
            ->where('workbench.name', 'Empty Project')
            ->has('views', 0)
        );
});

it('REQ-M11-004: snapshot chrome links back to the project and labels the current snapshot as a view', function (): void {
    $src = file_get_contents(resource_path('js/pages/snapshot.tsx'));

    expect($src)
        ->toContain("show as showWorkbench")
        ->toContain('showWorkbench({')
        ->toContain('<Badge variant="secondary">View</Badge>');
});
