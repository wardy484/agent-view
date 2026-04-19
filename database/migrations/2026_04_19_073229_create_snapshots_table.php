<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workbench_id')->constrained('workbenches')->cascadeOnDelete();
            $table->string('slug');
            $table->string('title')->nullable();
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->timestamps();

            $table->unique(['workbench_id', 'slug']);
            $table->index('current_version_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('snapshots');
    }
};
