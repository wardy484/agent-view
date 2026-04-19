<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\SnapshotVersion;
use App\Nexus\Schemas\SlideDeckViewSchema;
use App\Nexus\Schemas\SlideDeckViewSchemaException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M3-001: validate() accepts a well-formed slide deck payload', function (): void {
    $payload = [
        'slides' => [
            ['title' => 'Intro', 'body_md' => '# Hello'],
            ['title' => 'Outro', 'body_md' => 'Thanks!'],
        ],
    ];

    expect(SlideDeckViewSchema::validate($payload))->toBe($payload);
});

it('REQ-M3-001: validate() rejects a payload missing slides', function (): void {
    expect(fn () => SlideDeckViewSchema::validate([]))
        ->toThrow(
            SlideDeckViewSchemaException::class,
            'data_payload.slides is required and must be a non-empty array.',
        );
});

it('REQ-M3-001: validate() rejects an empty slides array', function (): void {
    expect(fn () => SlideDeckViewSchema::validate(['slides' => []]))
        ->toThrow(
            SlideDeckViewSchemaException::class,
            'data_payload.slides is required and must be a non-empty array.',
        );
});

it('REQ-M3-001: validate() rejects slides missing title', function (): void {
    expect(fn () => SlideDeckViewSchema::validate([
        'slides' => [['body_md' => 'No title']],
    ]))->toThrow(
        SlideDeckViewSchemaException::class,
        'data_payload.slides[0].title is required and must be a string.',
    );
});

it('REQ-M3-001: validate() rejects slides missing body_md', function (): void {
    expect(fn () => SlideDeckViewSchema::validate([
        'slides' => [['title' => 'No body']],
    ]))->toThrow(
        SlideDeckViewSchemaException::class,
        'data_payload.slides[0].body_md is required and must be a string.',
    );
});

it('REQ-M3-001: MCP tool accepts view_type slide_deck and persists a version', function (): void {
    $arguments = [
        'workbench_slug' => 'deck-demo',
        'view_type' => 'slide_deck',
        'data_payload' => [
            'slides' => [
                ['title' => 'One', 'body_md' => 'First'],
                ['title' => 'Two', 'body_md' => 'Second'],
            ],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)->assertOk();

    expect(SnapshotVersion::query()->where('view_type', 'slide_deck')->count())->toBe(1);
});

it('REQ-M3-001: MCP tool rejects a slide deck payload missing slides', function (): void {
    $arguments = [
        'workbench_slug' => 'deck-demo-bad',
        'view_type' => 'slide_deck',
        'data_payload' => [
            'slides' => [],
        ],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.slides is required and must be a non-empty array.',
        ]);

    expect(SnapshotVersion::query()->count())->toBe(0);
});
