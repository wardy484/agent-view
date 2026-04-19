<?php

declare(strict_types=1);

use App\Nexus\Schemas\FlowchartViewSchema;
use App\Nexus\Schemas\FlowchartViewSchemaException;

it('REQ-M2-004: validate() accepts a well-formed mermaid_source', function (): void {
    $payload = ['mermaid_source' => "flowchart TD\nA --> B"];

    $validated = FlowchartViewSchema::validate($payload);

    expect($validated)->toBe($payload);
});

it('REQ-M2-004: validate() rejects a missing mermaid_source', function (): void {
    expect(fn () => FlowchartViewSchema::validate([]))
        ->toThrow(
            FlowchartViewSchemaException::class,
            'data_payload.mermaid_source is required and must be a string.',
        );
});

it('REQ-M2-004: validate() rejects a non-string mermaid_source', function (): void {
    expect(fn () => FlowchartViewSchema::validate(['mermaid_source' => ['array', 'not string']]))
        ->toThrow(
            FlowchartViewSchemaException::class,
            'data_payload.mermaid_source is required and must be a string.',
        );
});

it('REQ-M2-004: validate() rejects an empty mermaid_source', function (): void {
    expect(fn () => FlowchartViewSchema::validate(['mermaid_source' => '   ']))
        ->toThrow(
            FlowchartViewSchemaException::class,
            'data_payload.mermaid_source must not be empty.',
        );
});
