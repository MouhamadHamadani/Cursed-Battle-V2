<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-003: agility splits into speed (hit chance + turn order) and dexterity
 * (dodge). Add → backfill → drop, so this is correct on a populated database
 * and not just on a disposable dev one. Both new columns take the SAME old
 * value: halving it would silently nerf every existing character.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('speed')->default(5)->after('defense');
            $table->unsignedInteger('dexterity')->default(5)->after('speed');
        });

        DB::table('characters')->update([
            'speed' => DB::raw('agility'),
            'dexterity' => DB::raw('agility'),
        ]);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn('agility');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->integer('speed_delta')->default(0)->after('defense_delta');
            $table->integer('dexterity_delta')->default(0)->after('speed_delta');
        });

        DB::table('items')->update([
            'speed_delta' => DB::raw('agility_delta'),
            'dexterity_delta' => DB::raw('agility_delta'),
        ]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('agility_delta');
        });
    }

    /**
     * Symmetric: agility comes back from speed (dexterity's value is the same
     * on any row this migration created, and is the loser on a hand-diverged
     * one — a lossy reverse is inherent to un-splitting two columns into one).
     */
    public function down(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->unsignedInteger('agility')->default(5)->after('defense');
        });

        DB::table('characters')->update(['agility' => DB::raw('speed')]);

        Schema::table('characters', function (Blueprint $table) {
            $table->dropColumn(['speed', 'dexterity']);
        });

        Schema::table('items', function (Blueprint $table) {
            $table->integer('agility_delta')->default(0)->after('defense_delta');
        });

        DB::table('items')->update(['agility_delta' => DB::raw('speed_delta')]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn(['speed_delta', 'dexterity_delta']);
        });
    }
};
