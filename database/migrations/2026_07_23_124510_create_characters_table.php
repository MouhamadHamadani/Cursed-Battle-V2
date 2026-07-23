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
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // Defaults are game-balance knobs, tune post-MVP.
            $table->unsignedInteger('level')->default(1);
            $table->unsignedBigInteger('xp')->default(0);
            $table->unsignedBigInteger('gold')->default(100);
            $table->unsignedInteger('health')->default(100);
            $table->unsignedInteger('max_health')->default(100);
            $table->unsignedInteger('energy')->default(10);
            $table->unsignedInteger('max_energy')->default(10);
            $table->unsignedInteger('strength')->default(5);
            $table->unsignedInteger('defense')->default(5);
            $table->unsignedInteger('agility')->default(5);
            $table->timestamp('hospitalized_until')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
