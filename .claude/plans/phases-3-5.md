# Cursed Battle v2 — Plan: Phases 3–5 (Work, Train, Market/Equip)

Status: APPROVED-PENDING. Executor: /do, one impl subagent + one verify subagent per phase, commit after each verified phase. Same protocol as phases-0-2.md.

Builds on: ed37b86, 85e80ce, 2c57b78 (Phases 0–2 done — L12.64 + LW3.8.2 + Breeze Blade, `characters` table, regen). 32 tests green, clean tree.

## Decisions locked this session (2026-07-23)
- **Work table renamed `jobs` → `occupations`** (model `Occupation`). CLAUDE.md's data model said `jobs`, but Laravel's queue owns a `jobs` table and this app runs `QUEUE_CONNECTION=database` + `SESSION_DRIVER=database` + `CACHE_STORE=database` — the framework `jobs`/`job_batches`/`failed_jobs`/`sessions`/`cache` tables are live infra. Hard MySQL collision. User approved `occupations`. Only the NAME changes; columns (name, description, min_level, max_level, gold_per_energy) are per CLAUDE.md.
- **Hand-seed all content.** No `cursed battle weapons.xlsx` exists anywhere local (Glob'd project + BuildSyntax tree); old repo github.com/MouhamadHamadani/Cursed-Battle is unreachable per CLAUDE.md; `BuildSyntaxOld/` is the agency website, not the prototype. Both "confirm before assuming" sources came up empty — starter occupations/items are hand-authored below. Seed names are placeholder flavor, tune later. **Do NOT add an Excel dependency** (maatwebsite/excel / phpspreadsheet) for a one-time seed — climb the ladder, use a PHP array + `firstOrCreate` in a seeder.

## Phase 0 — Documentation Discovery (DONE 2026-07-23; sources: laravel.com/docs/12.x/{database,queries,seeding,eloquent-factories,eloquent}, livewire.laravel.com/docs/3.x/{actions,validation,computed-properties,wire-loading,events,components,upgrading})

### Allowed APIs (verified, with source)
| Concern | Current API | Source |
|---|---|---|
| Atomic conditional spend | `Model::where('id',$id)->where('energy','>=',$cost)->update([...DB::raw('energy - N')])` returns affected-row **int** — 0 ⇒ guard failed | 12.x/queries "update() returns affected count" |
| Multi-write atomicity | `DB::transaction(fn() => ...)`; throw inside ⇒ auto-rollback; `attempts:` named arg for deadlock retry | 12.x/database |
| Pessimistic lock | `->lockForUpdate()->first()` inside a transaction (canonical transfer example) | 12.x/queries "Pessimistic Locking" |
| Decrement | `->decrement('gold', $n)` (also `increment`, 3rd arg = extra cols) | 12.x/queries |
| LW3 action | `wire:click="buy({{ $item->id }})"`, `wire:submit="work"` (auto-preventDefault, no `.prevent`) | 3.x/actions |
| LW3 validate | inline `$validated = $this->validate([...])` in the action; or `rules()` method; or `#[Validate('...')]` attr | 3.x/validation |
| LW3 live feedback | `#[Computed]` method (recomputes each request → reflects post-action state); read in Blade as `$this->prop` | 3.x/computed-properties |
| LW3 no-double-submit | `wire:loading.attr="disabled"` + `wire:target="work"` | 3.x/wire-loading |
| LW3 flash | `session()->flash('status', '...')` + `@if(session('status'))` renders (same round-trip re-render) | established; no dedicated 3.x page |
| Full-page component | `Route::get('/work', Work::class)`; `#[Layout('layouts.app')]` on class/render (Breeze ships `layouts.app`) | 3.x/components |
| Factories | `HasFactory` trait; `Model::factory()->create([...])` | 12.x/eloquent-factories |
| Idempotent seed | `Model::firstOrCreate([key],[defaults])` / `updateOrCreate` | 12.x/eloquent |

### 🔒 SECURITY SPINE (verbatim from 3.x/actions — non-negotiable for every spend action)
> "any public method in your Livewire component can be called from the client-side, even without an associated `wire:click`" and "Action parameters should be treated just like HTTP request input... should not be trusted."

⇒ **Every service call re-validates server-side**: ownership, affordability, level gate, energy. The UI (disabled buttons, hidden actions) is NOT a security boundary. All spends are atomic (conditional UPDATE affected-count, or transaction+lock) so a replayed/concurrent client call can never overspend energy or gold. This is the whole point of putting the logic in services.

### Anti-pattern guards (grep-checkable — LW2/L11 patterns that must NOT appear)
- `$this->emit(`/`emitTo(`/`dispatchBrowserEvent(` → LW3 is `$this->dispatch('e', named: $arg)` (params must be named)
- `wire:model.defer`/`.lazy` → default is deferred; `.live` for live, `.blur` replaces `.lazy`
- `App\Http\Livewire` → `App\Livewire`
- `LEAST(` → sqlite tests lack it (Phase C lesson); use plain arithmetic + `WHERE` guard
- game math inside a Livewire component or the seeder → belongs in `app/Services/`
- raw client string as a SQL column name → stat name must come from a hardcoded whitelist

### Shared building block (build once in Phase E, reuse in F/G)
`app/Services/GameActionException.php` — `class GameActionException extends \RuntimeException {}`. Services throw it on any validation/affordability failure (message is user-facing). Components: `try { $service->...(); session()->flash('status', 'Success msg'); } catch (GameActionException $e) { session()->flash('error', $e->getMessage()); }`. One class, no per-service hierarchy. Lives under Services so the game "brain" stays self-contained (survives client swap).

---

## Phase E — Work (CLAUDE.md Phase 3)

**Design:** character picks an `occupation` they qualify for (level in [min_level, max_level]), clicks **Work a shift** → spends ALL current energy, earns `energy × gold_per_energy` gold. This gives the regen loop a purpose (regen → work → gold). Spend-all avoids a client-supplied amount (smaller attack surface).

1. `php artisan make:model Occupation -mf` (model + migration + factory). Migration:
   - id()
   - string('name'); text('description')
   - unsignedInteger('min_level')->default(1)
   - unsignedInteger('max_level')->nullable()   // null = no upper cap
   - unsignedInteger('gold_per_energy')
   - timestamps()
2. `Occupation` model: `$guarded = []`. (No relation to Character needed for MVP — work picks any occupation the level allows; not a stored assignment.)
3. `app/Services/GameActionException.php` (shared, per above).
4. `app/Services/WorkService.php`:
   ```php
   public function work(Character $character, Occupation $occupation): array
   {
       // level gate (max_level null = uncapped)
       if ($character->level < $occupation->min_level ||
           ($occupation->max_level !== null && $character->level > $occupation->max_level)) {
           throw new GameActionException('You are not the right level for this work.');
       }
       $rate = (int) $occupation->gold_per_energy;               // from DB, cast defensively
       // atomic: earn energy*rate gold, zero energy — single statement, RHS uses pre-update energy
       $affected = Character::whereKey($character->id)->where('energy', '>', 0)->update([
           'gold'   => DB::raw('gold + (energy * '.$rate.')'),
           'energy' => 0,
       ]);
       if ($affected === 0) {
           throw new GameActionException('You have no energy to work.');
       }
       $character->refresh();
       return ['energy_spent' => /* pre-spend energy */, 'gold_earned' => /* energy_spent*rate */, 'character' => $character];
   }
   ```
   NOTE for implementer: capture pre-spend energy BEFORE the update (read `$character->energy` or re-select) to report `energy_spent`/`gold_earned` in the result — the atomic UPDATE itself doesn't return it. Portable arithmetic only (no LEAST). No transaction needed — single atomic UPDATE.
5. `app/Livewire/Work.php` (full-page, `#[Layout('layouts.app')]`): list occupations the character qualifies for (and show locked ones greyed with level requirement). `work(int $occupationId)` action → load occupation, call `WorkService::work`, flash result ("You earned N gold"), catch `GameActionException` → flash error. `#[Computed]` character for live energy/gold display. Button `wire:click="work({{ $o->id }})" wire:loading.attr="disabled" wire:target="work"`.
6. `resources/views/livewire/work.blade.php`: Breeze dark Tailwind. Occupation cards (name, description, level range, gold/energy rate, Work button). Current energy/gold banner. `@if(session('status'))`/`@if(session('error'))` flash blocks.
7. `OccupationSeeder` (hand-authored, idempotent `firstOrCreate` keyed on name):
   | name | min | max | gold_per_energy |
   |---|---|---|---|
   | Grave Digger | 1 | 5 | 2 |
   | Cursed Courier | 3 | 10 | 4 |
   | Bone Merchant | 8 | 20 | 7 |
   | Soul Broker | 15 | null | 12 |
   Register in `DatabaseSeeder::run()` via `$this->call([OccupationSeeder::class])`.
8. Route `Route::get('/work', Work::class)->middleware(['auth','verified'])->name('work')` in routes/web.php. Add **Work** link to `resources/views/layouts/navigation.blade.php` (both desktop + responsive nav, copy Breeze's `x-nav-link` pattern).
9. `php artisan migrate` + `php artisan db:seed --class=OccupationSeeder`.
10. `tests/Feature/WorkServiceTest.php` (Pest, RefreshDatabase auto via Pest.php): qualified char with energy → gold == energy×rate, energy == 0; under min_level → GameActionException, no change; over max_level → rejected; zero energy → rejected (no free gold); occupation with null max_level → high-level char allowed.

**Verify:** `php artisan test` green (32 + new); occupations seeded; /work renders qualifying jobs; manual: set energy 8, work Grave Digger (rate 2) → +16 gold, energy 0; work again at 0 energy → error flash, no gold change. Grep: no LEAST, no game math in Work.php.

---

## Phase F — Train (CLAUDE.md Phase 4)

**Design:** spend a fixed energy cost to raise one chosen stat (strength/defense/agility) by 1. No new table — mutates the existing `characters` stat columns.

1. `app/Services/TrainingService.php`:
   ```php
   public const ENERGY_COST = 5;   // per session, tunable
   public const STAT_GAIN   = 1;
   private const STATS = ['strength', 'defense', 'agility'];   // whitelist — client stat name validated against this

   public function train(Character $character, string $stat): array
   {
       if (!in_array($stat, self::STATS, true)) {
           throw new GameActionException('Unknown stat.');   // never let client string reach SQL column
       }
       $affected = Character::whereKey($character->id)
           ->where('energy', '>=', self::ENERGY_COST)
           ->update([
               $stat    => DB::raw($stat.' + '.self::STAT_GAIN),   // $stat is whitelisted ⇒ safe identifier
               'energy' => DB::raw('energy - '.self::ENERGY_COST),
           ]);
       if ($affected === 0) {
           throw new GameActionException('Not enough energy to train (need '.self::ENERGY_COST.').');
       }
       $character->refresh();
       return ['stat' => $stat, 'character' => $character];
   }
   ```
   Single atomic UPDATE, portable arithmetic, whitelist-gated column name.
2. `app/Livewire/Train.php` (full-page): three train buttons (Strength/Defense/Agility). `train(string $stat)` → `TrainingService::train`, flash "Strength is now N" / error. `#[Computed]` character for live stats + energy. Buttons `wire:loading.attr="disabled" wire:target="train"`.
3. `resources/views/livewire/train.blade.php`: Breeze dark. Current stats + energy, three train cards showing cost (`TrainingService::ENERGY_COST`), flash blocks.
4. Route `/train` (auth+verified) + **Train** nav link (both nav sections).
5. `tests/Feature/TrainingServiceTest.php`: energy ≥ cost → chosen stat +1, energy −cost, OTHER stats unchanged; energy == cost (boundary) → works, energy 0; energy == cost−1 → rejected, stat + energy unchanged; invalid stat name (e.g. 'level','max_health','gold') → GameActionException, nothing changes (proves whitelist blocks column-injection).

**Verify:** tests green; /train renders; manual: energy 10 → train strength → str+1, energy 5 → again → str+1, energy 0 → third attempt errors. Grep: no raw client stat → SQL; no math in Train.php.

---

## Phase G — Market / Equip (CLAUDE.md Phase 5)

**Design:** buy items with gold (level-gated), own them (one row per item), equip one weapon + one armor at a time. Equipped stat deltas are display-only for now; **combat integration is Phase 6** — effective-stat aggregation (base + equipped deltas) is deliberately NOT built here (YAGNI until CombatService needs it; building it twice risks divergence). The market page lists each equipped item's deltas so the effect is visible.

1. `php artisan make:model Item -mf` and `php artisan make:model CharacterItem -m`.
   **items** migration:
   - id(); string('name'); string('type')   // 'weapon' | 'armor' (validated in seeder/service, not a DB enum — keep it a string)
   - integer('strength_delta')->default(0); integer('defense_delta')->default(0); integer('agility_delta')->default(0)   // signed
   - unsignedInteger('min_level')->default(1); unsignedInteger('cost'); string('image')->nullable()   // no art assets yet → null
   - timestamps()
   **character_items** migration:
   - id()
   - foreignId('character_id')->constrained()->cascadeOnDelete()
   - foreignId('item_id')->constrained()->cascadeOnDelete()
   - boolean('equipped')->default(false)
   - timestamps()
   - `$table->unique(['character_id','item_id']);`   // one row per owned item ⇒ no duplicate ownership
2. Models: `Item` (`$guarded=[]`, `items(): HasMany` optional), `CharacterItem` (`$guarded=[]`, `item(): BelongsTo`, `character(): BelongsTo`, `casts()` → `['equipped'=>'boolean']`). Add `items(): HasMany` / `characterItems()` to `Character` as needed.
3. `app/Services/MarketService.php`:
   ```php
   public function buy(Character $character, Item $item): CharacterItem
   {
       return DB::transaction(function () use ($character, $item) {
           $fresh = Character::whereKey($character->id)->lockForUpdate()->firstOrFail();   // serialize concurrent buys
           if ($fresh->level < $item->min_level)  throw new GameActionException('Your level is too low for this item.');
           if (CharacterItem::where('character_id',$fresh->id)->where('item_id',$item->id)->exists())
                                                    throw new GameActionException('You already own this item.');
           if ($fresh->gold < $item->cost)        throw new GameActionException('Not enough gold.');
           $fresh->decrement('gold', $item->cost);
           return CharacterItem::create(['character_id'=>$fresh->id, 'item_id'=>$item->id, 'equipped'=>false]);
       });
   }

   public function equip(Character $character, Item $item): void
   {
       DB::transaction(function () use ($character, $item) {
           $owned = CharacterItem::where('character_id',$character->id)->where('item_id',$item->id)->first();
           if (!$owned) throw new GameActionException('You do not own this item.');
           // one equipped per type: unequip current same-type item(s) first
           CharacterItem::where('character_id',$character->id)
               ->whereHas('item', fn($q) => $q->where('type', $item->type))
               ->update(['equipped'=>false]);
           $owned->update(['equipped'=>true]);
       });
   }

   public function unequip(Character $character, Item $item): void
   {
       CharacterItem::where('character_id',$character->id)->where('item_id',$item->id)
           ->update(['equipped'=>false]);
   }
   ```
   Buy = transaction + `lockForUpdate` (doc-backed multi-write pattern: decrement gold + insert pivot must be atomic). Equip = transaction (unequip same-type + equip).
4. `app/Livewire/Market.php` (full-page): two sections — **Shop** (all items; buy button, greyed if owned/under-level/unaffordable but server still re-checks) and **Inventory** (owned items; equip/unequip toggle). Actions `buy(int $itemId)`, `equip(int $itemId)`, `unequip(int $itemId)` → MarketService, flash success/error. `#[Computed]` for character (gold/level) and owned-items map. `wire:loading.attr="disabled"` on every button with `wire:target`.
5. `resources/views/livewire/market.blade.php`: Breeze dark. Shop grid (name, type, deltas like "+5 STR", min level, cost, Buy) + Inventory grid (owned items, Equip/Unequip, equipped badge). Flash blocks.
6. `ItemSeeder` (hand-authored, idempotent `firstOrCreate` on name):
   | name | type | str | def | agi | min_lvl | cost |
   |---|---|---|---|---|---|---|
   | Rusty Dagger | weapon | 2 | 0 | 0 | 1 | 50 |
   | Leather Vest | armor | 0 | 2 | 1 | 1 | 80 |
   | Bone Shield | armor | 0 | 4 | 0 | 2 | 150 |
   | Iron Sword | weapon | 5 | 0 | 0 | 3 | 200 |
   | Plate Armor | armor | 0 | 8 | 0 | 8 | 500 |
   | Cursed Blade | weapon | 9 | 0 | 2 | 8 | 600 |
   image = null (no assets). Register in DatabaseSeeder.
7. Routes `/market` (auth+verified) + **Market** nav link (both nav sections).
8. `php artisan migrate` + seed.
9. `tests/Feature/MarketServiceTest.php`: buy with gold+level → owned row exists, gold −cost; insufficient gold → rejected, no row, gold unchanged; under min_level → rejected; duplicate buy → rejected (unique + guard); equip → equipped=true AND prior same-type item equipped=false (swap works); equip cross-type keeps other type equipped; equip un-owned item → rejected; unequip → equipped=false.

**Verify:** tests green (all prior + new); items seeded; /market renders shop+inventory; manual: buy Rusty Dagger with 100 gold → gold 50, owned; equip it; buy+equip Iron Sword needs level 3 → rejected at level 1; equip two weapons → only latest equipped. Grep: no game math in Market.php; buy/equip wrapped in DB::transaction.

---

## Phase H — Final verification sweep

1. ANTI-PATTERN SWEEP (zero hits): `App\Http\Livewire`; `$this->emit(`/`emitTo(`/`dispatchBrowserEvent(`; `wire:model.defer`/`.lazy`; `LEAST(` in app/; `protected $casts` property in app/; game math (arithmetic on gold/energy/stats, DB::raw spend) inside any `app/Livewire/*` component; raw non-whitelisted client value as SQL column; `while(true)`/`sleep(`; `app/Console/Kernel.php` absent.
2. ARCHITECTURE AUDIT: all spend/economy logic lives in `app/Services/{Work,Training,Market}Service.php` — components only call services + flash. Every service action re-validates server-side (level/afford/own/energy) — confirm the security spine holds (grep each component's public action methods → each routes to a service, none mutate the DB directly).
3. ATOMICITY SPOT-CHECK: WorkService/TrainingService use single conditional UPDATE with affected-count guard; MarketService buy/equip use DB::transaction (+ lockForUpdate on buy). No read-then-write-without-guard on gold/energy anywhere.
4. VERSION PINS unchanged: laravel/framework 12.x, livewire/livewire 3.x.
5. `php artisan migrate:fresh --seed` → clean rebuild works; `php artisan test` full suite green.
6. END-TO-END on real MySQL: register → regen/set energy → work (gold up, energy 0) → set energy → train (stat up) → buy item (gold down, owned) → equip (swap works) → all persisted. Clean up throwaway rows.
7. SCOPE AUDIT: nothing beyond Phases 3–5 built — no combat resolution (`CombatService`), no quests/tournaments/clans/classes, no leveling-up logic yet (Phase 8), no hospital (Phase 7). Forward-reference comments OK; implementations not.
8. GIT: one commit per phase (E, F, G), tree clean.

## Out of scope — flag, don't build
Combat/`CombatService` (Phase 6 — needs `/architecture` ADR first), hospital cooldown (7), leveling/XP-on-work-or-combat (8), effective-stat aggregation for combat (Phase 6), quests/tournaments/clans/class system/MENA theming/monetization (never, per CLAUDE.md). XP is currently a static column — do NOT wire XP gain into Work/Train here unless the plan is revised; leveling is Phase 8.

## Suggested commit messages
- E: `feat: occupations table + WorkService (energy→gold), work page`
- F: `feat: TrainingService (energy→stat) + train page`
- G: `feat: items + character_items, MarketService buy/equip, market page`
