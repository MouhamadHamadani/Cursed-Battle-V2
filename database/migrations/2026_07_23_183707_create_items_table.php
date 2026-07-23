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
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // 'weapon' | 'armor' — validated in service, not a DB enum
            $table->integer('strength_delta')->default(0); // signed
            $table->integer('defense_delta')->default(0);
            $table->integer('agility_delta')->default(0);
            $table->unsignedInteger('min_level')->default(1);
            $table->unsignedInteger('cost');
            $table->string('image')->nullable(); // no art assets yet
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
