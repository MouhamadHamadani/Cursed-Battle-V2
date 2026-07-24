<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('combat_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attacker_id')->constrained('characters')->cascadeOnDelete();
            $table->foreignId('defender_id')->constrained('characters')->cascadeOnDelete();
            $table->unsignedInteger('attacker_level');
            $table->unsignedInteger('defender_level');
            $table->json('attacker_stats'); // effective stats + starting HP snapshot
            $table->json('defender_stats');
            $table->json('events'); // round-by-round
            $table->foreignId('winner_id')->nullable()->constrained('characters')->nullOnDelete();
            $table->integer('gold_change'); // attacker's perspective (signed)
            $table->integer('xp_change'); // attacker's perspective
            $table->timestamp('created_at')->useCurrent(); // append-only; no updated_at
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('combat_logs');
    }
};
