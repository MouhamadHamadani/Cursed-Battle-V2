<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faction selection (MVP scope addition, 2026-08-30).
 *
 * Keys stay generic (`faction_1` / `faction_2`) so display copy — still TBD —
 * can change without touching schema, queries, or seeds.
 *
 * characters.faction: picked once at creation, immutable for MVP. The default
 * exists to backfill rows that predate this column; the real gate is the
 * required pick validated at registration.
 *
 * items.faction: NULL = universal, sold to everyone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('characters', function (Blueprint $table) {
            $table->string('faction', 16)->default('faction_1')->after('user_id');
        });

        Schema::table('items', function (Blueprint $table) {
            $table->string('faction', 16)->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('characters', fn (Blueprint $table) => $table->dropColumn('faction'));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('faction'));
    }
};
