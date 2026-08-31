<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single-opponent reveal + escalating re-roll cost.
 *
 * Both columns live on `characters` — not the session, not component state —
 * because the whole point of the mechanic is that a refresh or a logout does
 * not hand the player a free re-roll.
 *
 * opponent_id: the mark currently revealed. Self-referencing, nullOnDelete so
 * a deleted character clears every reveal pointing at it without app code.
 * opponent_rerolls: how many re-rolls have been bought since the last
 * committed fight. OpponentService prices the next one off this; CombatService
 * zeroes it when a fight is committed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->foreignId('opponent_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->unsignedInteger('opponent_rerolls')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opponent_id');
            $table->dropColumn('opponent_rerolls');
        });
    }
};
