<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Timed Train/Work sessions (ADR-002 §3). Live state lives in nullable
 * columns on `characters` rather than a side table so "at most one activity
 * per character" is structurally unrepresentable, and so the idempotent
 * resolution stays a single-table UPDATE.
 *
 * No index on activity_completes_at and no activity_started_at: every read is
 * whereKey-scoped and nothing needs elapsed time (ADR-002 §3).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('activity_type', 16)->nullable();              // 'train' | 'work'
            $table->string('activity_stat', 16)->nullable();              // train: strength|defense|agility
            $table->foreignId('activity_occupation_id')->nullable()
                ->constrained('occupations')->nullOnDelete();             // work: display/provenance only
            $table->unsignedInteger('activity_energy_spent')->nullable(); // snapshot: payout + XP basis
            $table->unsignedInteger('activity_gold_rate')->nullable();    // snapshot of gold_per_energy
            $table->timestamp('activity_completes_at')->nullable();       // the lazy check
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('activity_occupation_id');
            $table->dropColumn([
                'activity_type',
                'activity_stat',
                'activity_energy_spent',
                'activity_gold_rate',
                'activity_completes_at',
            ]);
        });
    }
};
