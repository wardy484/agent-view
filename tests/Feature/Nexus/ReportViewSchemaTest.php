<?php

declare(strict_types=1);

use App\Mcp\Servers\NexusServer;
use App\Mcp\Tools\PresentStructuredData;
use App\Models\SnapshotVersion;
use App\Nexus\Schemas\ReportViewSchema;
use App\Nexus\Schemas\ReportViewSchemaException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('REQ-M5-001: validate() accepts a markdown-only report payload', function (): void {
    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '# Q4 Summary'],
            ['type' => 'markdown', 'body' => '## Pipeline'],
        ],
    ];

    expect(ReportViewSchema::validate($payload))->toBe($payload);
});

it('REQ-M5-001: validate() accepts mixed markdown and embed blocks', function (): void {
    $payload = [
        'blocks' => [
            ['type' => 'markdown', 'body' => '## Intro'],
            ['type' => 'embed', 'snapshot_id' => 42],
            ['type' => 'markdown', 'body' => '## Outro'],
        ],
    ];

    expect(ReportViewSchema::validate($payload))->toBe($payload);
});

it('REQ-M5-001: validate() rejects a payload missing blocks', function (): void {
    expect(fn () => ReportViewSchema::validate([]))
        ->toThrow(
            ReportViewSchemaException::class,
            'data_payload.blocks is required and must be a non-empty array.',
        );
});

it('REQ-M5-001: validate() rejects an empty blocks array', function (): void {
    expect(fn () => ReportViewSchema::validate(['blocks' => []]))
        ->toThrow(
            ReportViewSchemaException::class,
            'data_payload.blocks is required and must be a non-empty array.',
        );
});

it('REQ-M5-001: validate() rejects a non-object block', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => ['not-an-object'],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0] must be an object.',
    );
});

it('REQ-M5-001: validate() rejects a block missing type', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['body' => 'orphan']],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].type is required and must be one of: markdown, embed.',
    );
});

it('REQ-M5-001: validate() rejects a block with an unknown type', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['type' => 'image', 'src' => 'foo.png']],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].type is required and must be one of: markdown, embed.',
    );
});

it('REQ-M5-001: validate() rejects a markdown block without a body', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['type' => 'markdown']],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].body is required and must be a string.',
    );
});

it('REQ-M5-001: validate() rejects a markdown block whose body is not a string', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['type' => 'markdown', 'body' => 123]],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].body is required and must be a string.',
    );
});

it('REQ-M5-001: validate() rejects an embed block without snapshot_id', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['type' => 'embed']],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].snapshot_id is required and must be an int.',
    );
});

it('REQ-M5-001: validate() rejects an embed block whose snapshot_id is not an int', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [['type' => 'embed', 'snapshot_id' => 'not-an-int']],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[0].snapshot_id is required and must be an int.',
    );
});

it('REQ-M5-001: validate() reports the offending block index', function (): void {
    expect(fn () => ReportViewSchema::validate([
        'blocks' => [
            ['type' => 'markdown', 'body' => 'ok'],
            ['type' => 'markdown', 'body' => 'ok'],
            ['type' => 'embed', 'snapshot_id' => 'bad'],
        ],
    ]))->toThrow(
        ReportViewSchemaException::class,
        'data_payload.blocks[2].snapshot_id is required and must be an int.',
    );
});

it('REQ-M5-001: MCP tool rejects a report payload missing blocks', function (): void {
    $arguments = [
        'workbench_slug' => 'reports-bad',
        'view_type' => 'report',
        'data_payload' => [],
    ];

    NexusServer::tool(PresentStructuredData::class, $arguments)
        ->assertHasErrors([
            'data_payload.blocks is required and must be a non-empty array.',
        ]);

    expect(SnapshotVersion::query()->count())->toBe(0);
});
