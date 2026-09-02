<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ADR-003: items.type ('weapon'|'armor') is replaced by items.slot
 * ('weapon'|'shield'|'head'|'body'). Every existing armor row maps to `body`
 * — mapping by name instead would be the hardcoded-category-name pattern this
 * project already rejected once, so ItemSeeder (updateOrCreate) is what moves
 * the Bone Shield into its real slot.
 *
 * Added nullable and only made NOT NULL after the backfill, deliberately:
 * there is no sensible default for a mechanical field, and a DEFAULT 'body'
 * would let an Item::create() that forgets `slot` silently produce armor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('slot', 16)->nullable()->after('name');
        });

        DB::table('items')->where('type', 'weapon')->update(['slot' => 'weapon']);
        DB::table('items')->where('type', '!=', 'weapon')->update(['slot' => 'body']);

        Schema::table('items', function (Blueprint $table) {
            $table->string('slot', 16)->nullable(false)->change();
            $table->dropColumn('type');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
        });

        DB::table('items')->where('slot', 'weapon')->update(['type' => 'weapon']);
        DB::table('items')->where('slot', '!=', 'weapon')->update(['type' => 'armor']);

        Schema::table('items', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
            $table->dropColumn('slot');
        });
    }
};
