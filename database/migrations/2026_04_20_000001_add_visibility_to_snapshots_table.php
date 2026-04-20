<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * REQ-M4-001: snapshots carry a visibility enum (private|link|shared).
 * Defaults to 'private' so existing rows remain non-shared after the
 * migration runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->string('visibility', 16)->default('private')->after('title');
            $table->index('visibility');
        });
    }

    public function down(): void
    {
        Schema::table('snapshots', function (Blueprint $table): void {
            $table->dropIndex(['visibility']);
            $table->dropColumn('visibility');
        });
    }
};
