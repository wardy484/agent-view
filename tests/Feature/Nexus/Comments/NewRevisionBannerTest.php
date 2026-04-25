<?php

declare(strict_types=1);

/**
 * REQ-M6-018: new-revision banner on the snapshot page.
 *
 * The banner is purely a frontend concern — when polling (REQ-M6-016)
 * brings down a `currentRevision` greater than the rendered one, a
 * non-disruptive sticky bar prompts the viewer to navigate to the new
 * revision. Following the convention established by REQ-M6-014 / 016 /
 * 017, we assert the file shapes here rather than running a JSDom or
 * browser test.
 */
it('REQ-M6-018: NewRevisionBanner returns null when latest equals rendered', function (): void {
    $path = resource_path('js/components/nexus/new-revision-banner.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function NewRevisionBanner')
        ->toContain('renderedRevision: number')
        ->toContain('latestRevision: number')
        ->toContain('onView: () => void')
        ->toContain('onDismiss: () => void')
        ->toContain('if (latestRevision <= renderedRevision)')
        ->toContain('return null');
});

it('REQ-M6-018: NewRevisionBanner uses animate-pulse-once and listens for visibilitychange', function (): void {
    $path = resource_path('js/components/nexus/new-revision-banner.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('animate-pulse-once')
        ->toContain('Agent pushed v')
        ->toContain('view changes')
        ->toContain('sticky')
        ->toContain('visibilitychange')
        ->toContain("event.key === 'Escape'")
        ->toContain('onDismiss()')
        ->toContain('onView')
        ->toContain('removeEventListener');

    // The pulse-once keyframe must be defined in the global stylesheet so
    // Tailwind's class-name discovery picks it up.
    $css = (string) file_get_contents(resource_path('css/app.css'));
    expect($css)
        ->toContain('@keyframes nexus-pulse-once')
        ->toContain('.animate-pulse-once');
});

it('REQ-M6-018: useRevisionBanner navigates to ?revision={current} on view', function (): void {
    $path = resource_path('js/hooks/use-revision-banner.ts');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('export function useRevisionBanner')
        ->toContain('renderedRevision: number')
        ->toContain('currentRevision: number')
        ->toContain('snapshotSlug: string')
        ->toContain('workbenchSlug: string')
        ->toContain('router.visit')
        ->toContain('?revision=')
        ->toContain('/workbenches/')
        ->toContain('/snapshots/');
});

it('REQ-M6-018: useRevisionBanner records dismissed revision and re-enables on a higher one', function (): void {
    $path = resource_path('js/hooks/use-revision-banner.ts');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain('dismissedRevision')
        ->toContain('setDismissedRevision')
        ->toContain('currentRevision > dismissedRevision')
        ->toContain('show')
        ->toContain('onView')
        ->toContain('onDismiss');
});

it('REQ-M6-018: snapshot page renders the banner above the report column when not historical and not link-token', function (): void {
    $path = resource_path('js/pages/snapshot.tsx');
    $source = (string) file_get_contents($path);

    expect($source)
        ->toContain("import { NewRevisionBanner } from '@/components/nexus/new-revision-banner'")
        ->toContain("import { useRevisionBanner } from '@/hooks/use-revision-banner'")
        ->toContain('<NewRevisionBanner')
        ->toContain('isAuthenticated && !is_public_link && !is_historical_view')
        ->toContain('banner.show')
        ->toContain('banner.onView')
        ->toContain('banner.onDismiss');
});

it('REQ-M6-018: snapshot page captures rendered revision once on mount so partial reloads do not mutate it', function (): void {
    $path = resource_path('js/pages/snapshot.tsx');
    $source = (string) file_get_contents($path);

    // Lazy useState initialiser — captures the value on first render only,
    // never re-evaluating it when partial reloads bring down a fresh
    // `version.revision`. (React-recommended; useRef trips the
    // react-hooks/refs lint when read during render.)
    expect($source)
        ->toContain('useState<number>(() => props.version.revision)')
        ->toContain('const [renderedRevision]')
        ->toContain('renderedRevision={renderedRevision}');
});
