<?php

declare(strict_types=1);

/**
 * REQ-M6-029: ⌘+Enter / Ctrl+Enter submits the inline comment composer
 * (REQ-M6-027). Submit button label carries a `<kbd>` hint reflecting the
 * active platform shortcut. Pinned via file-shape assertions to match the
 * other M6 frontend REQs.
 */
it('REQ-M6-029: spec records the cmd+enter submit requirement', function (): void {
    $spec = (string) file_get_contents(base_path('docs/nexus-spec.md'));

    expect($spec)
        ->toContain('**REQ-M6-029**')
        ->toContain('⌘+Enter')
        ->toContain('Ctrl+Enter')
        ->toContain('navigator.platform')
        ->toContain('metaKeyShortcutLabel');
});

it('REQ-M6-029: platform helper exposes isMac and metaKeyShortcutLabel', function (): void {
    $path = resource_path('js/lib/platform.ts');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function isMac')
        ->toContain('export function metaKeyLabel')
        ->toContain('export function metaKeyShortcutLabel')
        // Mac detection prefers navigator.platform with userAgent fallback.
        ->toContain('navigator.platform')
        ->toContain('navigator.userAgent')
        // Returns the platform-specific glyph.
        ->toContain("'⌘'")
        ->toContain("'Ctrl'")
        ->toContain("'⌘↩'")
        ->toContain("'Ctrl+↵'");
});

it('REQ-M6-029: composer textareas attach an onKeyDown that submits on metaKey/ctrlKey + Enter', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        // Helper is imported.
        ->toContain("from '@/lib/platform'")
        ->toContain('metaKeyShortcutLabel')
        // Key handler scoped to the textarea(s) inside the composer.
        ->toContain('onKeyDown={onKeyDown}')
        // Handler triggers on Enter combined with metaKey or ctrlKey.
        ->toContain("event.key === 'Enter'")
        ->toContain('event.metaKey || event.ctrlKey')
        // Default is prevented before invoking the existing submit path.
        ->toContain('event.preventDefault()')
        // Submit handler reused (no duplicate router.post in the keydown).
        ->toContain('onSubmit()');
});

it('REQ-M6-029: Submit button shows a <kbd> shortcut hint via metaKeyShortcutLabel()', function (): void {
    $path = resource_path('js/components/nexus/comment-selection-pill.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('<kbd')
        ->toContain('metaKeyShortcutLabel()')
        ->toContain('comment-selection-pill-submit-kbd')
        // Disabled state of the Submit button feeds into the kbd styling.
        ->toContain('submitDisabled');
});
