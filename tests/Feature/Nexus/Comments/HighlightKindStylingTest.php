<?php

declare(strict_types=1);

/**
 * REQ-M6-033: persistent highlights (REQ-M6-028) render with status- AND
 * kind-aware colours. Suggestions paint indigo; deletions (suggestion with
 * proposed_text='') paint red with a strike-through; comments retain the
 * yellow tint. Tooltips include the kind alongside the existing
 * author + first-line content.
 */
it('REQ-M6-033: comment-highlight-overlay branches styling on (status, kind, proposed_text emptiness)', function (): void {
    $path = resource_path('js/components/nexus/comment-highlight-overlay.tsx');

    expect(file_exists($path))->toBeTrue();

    $source = (string) file_get_contents($path);

    expect($source)
        // The deletion helper exists and matches the spec semantics.
        ->toContain('function isDeletion(')
        ->toContain("comment.kind === 'suggestion'")
        ->toContain("(comment.proposed_text ?? '') === ''")
        // The class-for helper branches on kind + status.
        ->toContain('classForComment')
        // Open comments → yellow.
        ->toContain('bg-yellow-200/70 dark:bg-yellow-700/40 border-b-2 border-dotted border-yellow-500')
        // Open suggestions (non-deletion) → indigo.
        ->toContain('bg-indigo-200/70 dark:bg-indigo-900/50 border-b-2 border-dotted border-indigo-400')
        // Open suggestions (deletion) → red strike.
        ->toContain('bg-red-200/70 dark:bg-red-900/60 line-through decoration-red-500 border-b-2 border-dotted border-red-400')
        // Resolved → muted slate strike (existing).
        ->toContain('bg-slate-300/60 dark:bg-slate-700/60 line-through decoration-slate-500 border-b-2 border-dotted border-slate-400')
        // Wontfix → grey strike (existing).
        ->toContain('bg-zinc-300/50 dark:bg-zinc-700/50 line-through opacity-70 border-b-2 border-dotted border-zinc-400')
        // The HighlightCommentSummary type now surfaces both kind and proposed_text.
        ->toContain("kind: 'comment' | 'suggestion'")
        ->toContain('proposed_text?: string')
        // The tooltip mentions the comment kind.
        ->toContain('${comment.author.display_name} (${comment.kind}):');
});
