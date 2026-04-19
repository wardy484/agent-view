## Requirement(s)

<!-- List every REQ-ID this PR closes (comma-separated). Title format:
     [REQ-M1-003] Monotonic revisions per snapshot -->

- REQ-XXX-000

## Tests added

<!-- Name each Pest `it(...)` added; every REQ-ID above must have one. -->

- `it('REQ-XXX-000: describe behaviour', ...)`

## Demo steps

<!-- One-line reproduction. A reviewer should be able to paste and run. -->

```bash
./scripts/worktree-bootstrap.sh <branch>
cd ../nexus-ui-worktrees/<branch>
php artisan test --filter=REQ-XXX-000
```

## Spec changes

<!-- If docs/nexus-spec.md changed, link the spec PR (should be separate). -->

- [ ] No spec changes
- [ ] Spec PR: #___

## Checklist

- [ ] `php artisan spec:check` passes locally
- [ ] `php artisan test` passes locally
- [ ] `./vendor/bin/pint --test` passes
- [ ] `pnpm lint && pnpm type-check` pass
- [ ] Worktree used (never mutate `main` working copy)
- [ ] No new unspec'd features
