# Cursed Battle v2 — Plan: Phases 6–8 (Combat, Hospital, Leveling)

Status: APPROVED-PENDING. Executor: /do, one impl subagent + one verify subagent per phase, commit after each verified phase. Same protocol as phases-0-2.md / phases-3-5.md.

**Design authority:** `.claude/adr/ADR-001-combat-hospital-leveling.md` — every mechanic, formula, and tunable comes from the ADR. This plan is the build sequence; the ADR is the spec. Read the ADR first.

Builds on: ed37b86 → 6261870 (Phases 0–5 done). 49 tests green, clean tree. Laravel 12.64, Livewire 3.8.2, Breeze, PHP 8.3.14, MySQL/InnoDB.

## Phase 0 — Documentation Discovery (DONE 2026-07-24; sources: php.net/manual, laravel.com/docs/12.x/{eloquent,queries,mocking}, livewire.laravel.com/docs/3.x/{properties,computed-properties,components}, carbon.nesbot.com)

### Allowed APIs (verified, with source) — the 5 combat-specific unknowns
| Concern | Current API | Source |
|---|---|---|
| Deterministic RNG seam | `new \Random\Randomizer(?\Random\Engine $engine=null)`; `->getInt(int $min,int $max): int` (closed interval). Prod: `new Randomizer()` (secure CSPRNG default). Tests: `new Randomizer(new \Random\Engine\Mt19937($seed))`. PHP 8.2+ (have 8.3.14). | php.net class.random-randomizer / .getint / class.random-engine-mt19937 |
| getInt edge cases | `getInt($min,$max)` inclusive both ends; `getInt(0,0)===0`; negatives OK; throws `ValueError` ONLY if `max<min` (our calls never do: `getInt(-level,level)` with level≥0, `getInt(1,100)`) | php.net random-randomizer.getint |
| created_at-only model | `public const UPDATED_AT = null;` → Eloquent manages created_at only, skips updated_at. Use WITH migration `$table->timestamp('created_at')->useCurrent()` and NO updated_at column. Do NOT use `$timestamps=false` (kills both). | laravel 12.x/eloquent#timestamps |
| Hold fight result in Livewire | Store a **plain nested array** (scalars + sub-arrays) as a public property — first-class supported type. Do NOT store Eloquent models as properties (select constraints not re-applied, id auto-locked). Use `#[Computed]` for the opponents list. | livewire 3.x/properties, /computed-properties |
| Loop rendering | `wire:key="..."` REQUIRED on the root element inside `@foreach` | livewire 3.x/components |
| Time control in tests | `$this->travel(31)->minutes()`, `$this->travelTo($carbon)`, `$this->travelBack()`, `$this->freezeTime()`. `now()->addMinutes(int)`, Carbon `->isFuture()`, `->diffForHumans()`. | laravel 12.x/mocking#interacting-with-time, carbon |
| Two-row lock | `Character::query()->lockForUpdate()->findOrFail($id)` twice inside one `DB::transaction` (canonical 2-row transfer example). | laravel 12.x/queries#pessimistic-locking |

### Flags folded into the plan
- **RNG reproducibility not stated verbatim** on php.net (it's the defining Mt19937 property) → Phase I adds ONE Pest test asserting two same-seed Randomizers yield identical `getInt` sequences (cheap insurance).
- **`UPDATED_AT=null` behavior** is type-legal (`@var string|null`) and established, though not spelled out in a sentence → the migration+model combo is confirmed valid; if `create()` ever complains about updated_at, that's the knob to check.
- **Ascending-id lock ordering is OUR convention** (InnoDB deadlock-avoidance best practice), NOT a Laravel-doc fact — correct, just not citable to laravel.com. Comment it as an InnoDB rationale, not a framework requirement.

### Anti-pattern guards (grep-checkable)
- `while(true)`/`while (true)` — combat loop is a bounded `for round in 1..MAX_ROUNDS`, NEVER a while-true (CLAUDE.md hard rule). Grep must stay clean.
- `LEAST(` — none (sqlite tests); combat math is PHP-side, not SQL.
- game math (damage/dodge/xp/level formulas) inside any `app/Livewire/*` — must live in services only.
- storing Eloquent models in Livewire public properties — use plain arrays / #[Computed].
- `App\Http\Livewire`, `$this->emit(`, `wire:model.defer/.lazy`, `protected $casts` property — all still banned.
- combat mutating characters OUTSIDE the locked transaction — all persistence inside `DB::transaction`.

---

## Phase I — Combat brain (ADR Phase 6, service layer — NO UI)

The "brain" (survives client swap). Everything except the Livewire page.

1. **RNG seam.** CombatService constructor-injects the RNG so tests are deterministic:
   ```php
   public function __construct(private \Random\Randomizer $rng = new \Random\Randomizer()) {}
   ```
   (PHP 8.1+ `new` in default param values — the no-arg default is a constant expression, valid.) Prod gets a secure CSPRNG; tests pass `new \Random\Randomizer(new \Random\Engine\Mt19937($seed))`. Two call sites only: `$this->rng->getInt(1,100)` (dodge roll), `$this->rng->getInt(-$level,$level)` (variance).

2. **`combat_logs` migration + `CombatLog` model** — copy the ADR §combat_logs block exactly (id; attacker_id/defender_id FK characters cascadeOnDelete; attacker_level/defender_level; json attacker_stats/defender_stats/events; winner_id nullable FK nullOnDelete; integer gold_change/xp_change; `$table->timestamp('created_at')->useCurrent()`, NO updated_at). Model `CombatLog`: `public const UPDATED_AT = null;`, `$guarded=[]`, `casts()` → `['attacker_stats'=>'array','defender_stats'=>'array','events'=>'array']`, `attacker()/defender()/winner()` BelongsTo(Character).

3. **`Character::isHospitalized(): bool`** → `return $this->hospitalized_until !== null && $this->hospitalized_until->isFuture();` (uses existing datetime cast). Confirm `characterItems(): HasMany` exists (added Phase G).

4. **`CombatService::effectiveStats(Character $c): array`** (public — resolves the Phase 5 deferral; Dashboard may reuse). Eager-load to avoid N+1: `$c->loadMissing('characterItems.item')`; sum base + equipped deltas:
   ```php
   return [
     'strength' => $c->strength + $equipped->sum(fn($ci)=>$ci->item->strength_delta),
     'defense'  => $c->defense  + $equipped->sum(fn($ci)=>$ci->item->defense_delta),
     'agility'  => $c->agility  + $equipped->sum(fn($ci)=>$ci->item->agility_delta),
   ];   // $equipped = characterItems where equipped==true
   ```

5. **`CombatService::resolve(Character $attacker, Character $defender): CombatResult`** — implement the ADR algorithm verbatim:
   - Pre-checks (throw `GameActionException` — reuse): not self; `!attacker->isHospitalized()`; `!defender->isHospitalized()`; `attacker->health > 0`.
   - `DB::transaction`: lock BOTH rows `lockForUpdate()->findOrFail()` in **ascending id order** (comment: InnoDB deadlock avoidance, our convention). Re-run pre-checks on fresh rows.
   - Sim: start HP = current health; order by effective agility desc (tie → attacker); `for $round=1..self::MAX_ROUNDS`; per actor: `dodge% = min(intdiv(targetAgi,2), self::DODGE_CAP)`, `if rng->getInt(1,100) <= dodge%` → dodge event; else `damage = max(self::MIN_DAMAGE, effStr - effDef + rng->getInt(-actorLevel,actorLevel))`, subtract, append event, KO check (`<=0` → winner=actor, break both loops).
   - No-KO after 10 rounds → higher remaining HP wins; exact tie → defender.
   - Persist (same txn): both healths `max(0,hp)`; loser `hospitalized_until = now()->addMinutes(self::HOSPITAL_MINUTES)`; gold `stolen = intval(floor(loser.gold * self::GOLD_STEAL_PCT))`, move; XP `xp_win = self::XP_BASE + loser.level*self::XP_PER_LEVEL`, halved if `winner.level > loser.level + self::FARM_GAP`, via `app(LevelingService::class)->awardXp($winner, $xp_win)`; write `CombatLog::create([...])` with effective-stat+startHP snapshots, events, winner_id, gold_change/xp_change from **attacker's perspective**.
   - Return `CombatResult`.
   - Constants (ADR §Tunables): `MAX_ROUNDS=10, DODGE_CAP=75, MIN_DAMAGE=1, GOLD_STEAL_PCT=0.10, HOSPITAL_MINUTES=30, XP_BASE=50, XP_PER_LEVEL=10, FARM_GAP=5`.

6. **`CombatResult` VO** (`app/Services/CombatResult.php`, readonly): winner, loser, attacker, defender (Characters), `array $events`, `int $goldChange`, `int $xpChange`, `bool $leveledUp`, `int $rounds`, `bool $knockout`. Plus **`toArray(): array`** → render-ready plain array (winnerName, loserName, outcome scalars, events, final HPs) for the Livewire property in Phase J. Models stay in the VO for programmatic callers; toArray flattens.

7. **`LevelingService::awardXp(Character $c, int $xp): array`** — SEAM, this phase INCREMENT-ONLY: `$c->increment('xp', $xp); return ['leveled_up'=>false, 'levels_gained'=>0];`. (Phase L fills in the level-up loop behind this exact signature — combat's call site never changes.) One comment: level-up processing lands in Phase 8.

8. **Tests** `tests/Feature/CombatServiceTest.php` (Pest; inject a **seeded** Randomizer for determinism; make outcomes deterministic via forcing stats — **defender agility 0 ⇒ never dodges**, and stat margins that swamp the ±level variance):
   - one-shot KO: attacker effStr 1000 vs defender 100 HP, def agi 0 → attacker wins, knockout true, ≤1 round.
   - defender wins on attacker weakness (attacker effStr 1 vs def def 1000 → attacker chips 1/round, 10 rounds, higher HP = defender wins).
   - **dodge cap pure check:** assert `effectiveDodgeChance(200) === 75` (expose a tiny pure method or test via a defender agi 200 fight) — the 75 cap (draft's bug fix).
   - min-damage floor: attacker effStr < defender effDef → damage still ≥ 1 (HP moves).
   - gold transfer: loser gold 100 → winner +10, loser −10 (GOLD_STEAL_PCT).
   - hospitalization: loser `hospitalized_until` set ~30 min out; `isHospitalized()` true.
   - persistence: both healths written (drained); winner xp incremented.
   - pre-check rejections: self-attack; attacker hospitalized (`travel` or set hospitalized_until future); defender hospitalized; attacker health 0 → each throws GameActionException, NOTHING persisted (assert no CombatLog row, no stat change).
   - atomic rollback: force a failure mid-resolve (e.g. a hospitalized defender discovered under lock) → assert zero side effects.
   - RNG reproducibility: two `Mt19937($seed)` Randomizers → identical `getInt` sequences (the flagged insurance test).
   - combat_log written: winner_id, gold_change, xp_change, events JSON round count.

**Verify:** `php artisan test` green (49 + new); combat_logs schema on live MySQL (FKs cascade/nullOnDelete, json columns, created_at only); `grep -n "while" app/Services/CombatService.php` → only the bounded `for`, no while(true); no game math outside services.

**Anti-pattern guards:** no while(true); no LEAST; combat math only in CombatService; all persistence inside the transaction; RNG injected (not global `rand()`/`mt_rand()` — grep app/Services for `\brand(`/`mt_rand(` → none).

---

## Phase J — Combat UI (ADR Phase 6, Livewire page)

1. `php artisan make:livewire Battle`. `app/Livewire/Battle.php` (`#[Layout('layouts.app')]`, mirror Work/Market):
   - `#[Computed] public function character()` → auth character.
   - `#[Computed] public function opponents()` → other characters, `whereKeyNot($this->character->id)`, excluding hospitalized (`->get()->reject->isHospitalized()` OR a `whereNull/where hospitalized_until <= now` query — prefer a query: `where('id','!=',me)->where(fn($q)=>$q->whereNull('hospitalized_until')->orWhere('hospitalized_until','<=',now()))`). Eager-load nothing heavy; show name/level.
   - `public array $lastFight = [];` (plain array — the render-safe result store).
   - `public function attack(int $defenderId): void` → `try { $result = app(CombatService::class)->resolve($this->character, Character::findOrFail($defenderId)); $this->lastFight = $result->toArray(); unset($this->character); } catch (GameActionException $e) { session()->flash('error', $e->getMessage()); }`. Server re-validates (self/hospitalized/health) inside resolve — the button list is not the security boundary.
   - NO combat math in the component.
2. `resources/views/livewire/battle.blade.php` (Breeze dark): current character banner (level, health/max_health, gold); flash error; opponents list (name, level, Attack button `wire:click="attack({{ $o->id }})" wire:loading.attr="disabled" wire:target="attack"`); result panel — when `$lastFight` non-empty, show outcome (winner, KO?, rounds, gold ±, xp +, "Leveled up!" if flagged) and the round-by-round `events` with `wire:key="round-{{ $e['round'] }}-{{ $e['actor'] }}-{{ $loop->index }}"`.
3. Route `/battle` (auth+verified) + **Battle** nav link (both desktop + responsive).
4. `tests/Feature/BattleTest.php`: acting user with a character attacks a seeded opponent → 200; a CombatLog row exists afterward; attacking a hospitalized opponent is rejected (flash error, no log); self not in opponents list. (Outcome determinism via forcing stats as in Phase I; the app-resolved CombatService uses the default secure RNG — assert on invariants, e.g. a log row exists and gold conserved, not exact damage.)

**Verify:** tests green; `/battle` route registered (route:list); nav shows Battle ×2; grep Battle.php for `DB::`/`->update`/damage math → none.

---

## Phase K — Hospital (ADR Phase 7)

Light — `isHospitalized()` + the combat block already exist from Phase I. This phase = UI + explicit tests + confirming the economy stays ungated.

1. `app/Livewire/Hospital.php` (`#[Layout]`, `#[Computed] character`): shows, when `isHospitalized()`, remaining time via `$character->hospitalized_until->diffForHumans()`; else "You are healthy." No actions (no pay-to-heal — post-MVP).
2. `resources/views/livewire/hospital.blade.php` + route `/hospital` (auth+verified) + nav link.
3. **Hospital banner** on the dashboard: in `resources/views/livewire/dashboard.blade.php`, if `isHospitalized()`, show a red "In hospital — free {{ diffForHumans }}" strip. (Dashboard is already a Livewire component — add the conditional, no logic.)
4. **Confirm Work/Train NOT gated** — no code change; add tests proving a hospitalized character can still work and train (guards live only in CombatService, per ADR).
5. `tests/Feature/HospitalTest.php`: `isHospitalized()` true when `hospitalized_until` future, false after `$this->travel(HOSPITAL_MINUTES+1)->minutes()`; combat blocked BOTH directions (attacker hospitalized → resolve throws; hospitalized defender excluded from opponents / resolve throws); WorkService->work and TrainingService->train SUCCEED while hospitalized (regression guard for the "ungated economy" decision).

**Verify:** tests green; `/hospital` route + nav; dashboard shows the banner when hospitalized (assert see `diffForHumans` text in a feature test with a hospitalized character); grep confirms Work/Train services have NO isHospitalized guard.

---

## Phase L — Leveling (ADR Phase 8)

1. **Enrich `LevelingService::awardXp`** with the level-up loop (same signature — combat call site unchanged):
   ```php
   $c->xp += $xp; $gained = 0;
   while ($c->xp >= $this->threshold($c->level)) {     // threshold(L) = L * self::XP_PER_LEVEL_STEP (100)
       $c->xp -= $this->threshold($c->level);
       $c->level++; $gained++;
       $c->max_health += self::HP_PER_LEVEL; $c->max_energy += self::EN_PER_LEVEL;
   }
   if ($gained > 0) { $c->health = $c->max_health; $c->energy = $c->max_energy; }  // heal+refill reward
   $c->save();
   return ['leveled_up'=>$gained>0, 'levels_gained'=>$gained];
   ```
   Constants: `XP_PER_LEVEL_STEP=100, HP_PER_LEVEL=10, EN_PER_LEVEL=1`. `xp` column = progress toward next level (resets on level-up), NOT lifetime. Bounded loop (xp strictly decreases) — terminates.
2. **Wire Work's XP trickle** — in `WorkService::work`, after the successful gold UPDATE, call `app(LevelingService::class)->awardXp($character, $energyBefore * self::XP_PER_ENERGY)` (`XP_PER_ENERGY=1`). Add the constant to WorkService. **Update the existing `WorkServiceTest`** to also assert xp increased after a shift (the current asserts on gold/energy still hold; add xp).
3. **Dashboard XP display**: show `xp / threshold(level)` progress (Dashboard reads it; threshold via `LevelingService`). Read-only.
4. `tests/Feature/LevelingServiceTest.php`: award below threshold → xp accrues, no level; award exactly threshold → level+1, xp 0, max_health/energy bumped, health/energy refilled to new max; award a huge lump → MULTI level-up with correct carryover (assert final level + leftover xp); heal-on-levelup verified (a damaged character at level-up ends at full new max_health). Also: work now awards xp (via updated WorkServiceTest).

**Verify:** tests green; combat still green (awardXp signature unchanged); dashboard shows xp progress; a work shift raises xp.

---

## Phase M — Final verification sweep + docs (ADR Phases 6–8 close-out)

1. ANTI-PATTERN SWEEP (zero hits): `while(true)`/`while (true)` in app/ (combat is a bounded `for`); `LEAST(` (comment-only ok); `App\\Http\\Livewire`; `->emit(`/`emitTo(`/`dispatchBrowserEvent(`; `wire:model.defer`/`.lazy`; `protected $casts` property; `\brand(`/`mt_rand(`/`random_int(` in app/Services (combat must use the injected Randomizer, not global RNG); `app/Console/Kernel.php` absent.
2. ARCHITECTURE AUDIT: grep app/Livewire/{Battle,Hospital}.php for `DB::`/`->update`/`->increment`/damage/xp/level arithmetic → none. All combat/leveling/effective-stat math in app/Services only. Components derive character from `auth()->user()` (server-side); only opponent/item ids come from the client; services re-validate.
3. ATOMICITY + BOUNDEDNESS: `resolve()` fully inside `DB::transaction` with two `lockForUpdate` (ascending id); the sim is a `for`-loop capped at MAX_ROUNDS (paste the loop header). Leveling loop is bounded by decreasing xp.
4. VERSION PINS unchanged: laravel/framework 12.x, livewire/livewire 3.x.
5. CLEAN REBUILD: `php artisan migrate:fresh --seed` succeeds; `php artisan test` full suite green (paste summary).
6. END-TO-END on real MySQL — the whole loop: two characters (attacker strong), attacker beats defender → defender health drained + `hospitalized_until` set + gold moved to attacker + attacker xp gained (maybe level-up with heal); defender now excluded from opponents / un-attackable (hospital block both ways); `travel` past HOSPITAL_MINUTES → defender attackable again; a Work shift raises attacker xp. Clean up throwaway rows; reference data (occupations/items) intact.
7. SCOPE AUDIT: MVP feature set complete (auth, character, work, train, market/equip, combat, hospital, leveling). Confirm nothing beyond CLAUDE.md MVP — no quests/tournaments/clans/class system/real-time combat/monetization/MENA theming. Forward-reference comments fine.
8. DOCS (ADR action item #6): 
   - Update `CLAUDE.md`: mark the "Combat — draft formula" section **superseded by ADR-001** (one line pointing to the ADR; do NOT delete the draft, annotate it). Note the `jobs`→`occupations` rename in the data-model section. **Minimal, surgical edits only** — this is the owner's decisions doc; flag the diff for review, don't rewrite.
   - Set ADR-001 Status: Proposed → **Accepted** (with the tunables as shipped, or the owner's adjusted values).
9. GIT: one commit per phase (I, J, K, L), plus the docs commit (M). Tree clean.

## Tunables — confirm at plan review (ADR §Tunables)
Defaults shipping unless the owner changes them: MAX_ROUNDS 10, DODGE_CAP 75, MIN_DAMAGE 1, GOLD_STEAL_PCT 0.10, HOSPITAL_MINUTES 30, XP_BASE 50 / XP_PER_LEVEL 10 / FARM_GAP 5, threshold L×100, HP_PER_LEVEL 10 / EN_PER_LEVEL 1, XP_PER_ENERGY 1. All isolated as service constants — trivially retunable later.

## Out of scope — flag, don't build
Level-range attack restrictions/respect, pay-to-heal, energy/nerve cost per attack, XP from Train, combat-log history preservation across account deletion, damage-scaled hospital time, matchmaking, and everything on CLAUDE.md's permanent do-not-build list. Combat-log history *display* (`/combat-logs` list) is OPTIONAL in Phase J — build only if trivial; no unread/`read`-flag system (CLAUDE.md dropped it).

## Suggested commit messages
- I: `feat: CombatService.resolve + combat_logs, effective stats, seeded RNG seam`
- J: `feat: battle page (opponent list, attack, round-by-round result)`
- K: `feat: hospital status page + cooldown block (combat only, economy ungated)`
- L: `feat: LevelingService level-up loop + work XP trickle`
- M: `docs: mark combat draft superseded by ADR-001; accept ADR-001`
