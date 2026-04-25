<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds a deterministic, idempotent user account for the /ui-review skill so an
 * LLM reviewer can authenticate against auth-gated pages (e.g. /dashboard,
 * /workbenches/*, /settings/*) without needing dynamic credentials.
 *
 * The credentials are intentionally stable and printed to stdout so the
 * skill can scrape them. NEVER promote this account to production — the
 * password is checked-in plaintext in the skill prompt.
 *
 *   php artisan nexus:seed-review-user
 */
class SeedReviewUser extends Command
{
    protected $signature = 'nexus:seed-review-user';

    protected $description = 'Create or refresh the deterministic UI-review user used by the /ui-review skill.';

    public const REVIEW_EMAIL = 'ui-reviewer@gentle-toucan.test';

    public const REVIEW_PASSWORD = 'ui-review-only-do-not-deploy';

    public function handle(): int
    {
        $user = User::query()->firstOrNew(['email' => self::REVIEW_EMAIL]);

        $user->name = 'UI Reviewer';
        $user->password = Hash::make(self::REVIEW_PASSWORD);

        if ($user->email_verified_at === null) {
            $user->email_verified_at = now();
        }

        $user->save();

        $this->line('email: '.self::REVIEW_EMAIL);
        $this->line('password: '.self::REVIEW_PASSWORD);
        $this->info('UI-review user ready (id='.$user->id.').');

        return self::SUCCESS;
    }
}
