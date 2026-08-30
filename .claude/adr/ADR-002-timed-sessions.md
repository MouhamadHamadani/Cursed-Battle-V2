# ADR-002: Timed Train & Work Sessions

**Status:** Proposed (2026-08-30) — awaiting owner sign-off on §Tunables and the three flagged forks
**Date:** 2026-08-30
**Deciders:** Mouhamad (project owner)
**Related:** [ADR-001](ADR-001-combat-hospital-leveling.md) — reuses its lazy-timestamp precedent (§Hospital) and leaves its combat/leveling tunables untouched.

---

## Context

Train and Work both resolve instantly today. `TrainingService::train()` spends 5 energy and returns with the stat already raised; `WorkService::work()` dumps the whole energy bar and returns with gold and the XP trickle already banked. Both are single atomic `UPDATE`s.

The owner wants both to take **real time**, scaling with character level — V1's "thou art at work, return when thy labours are complete" state — and, while a session runs, to lock the character out of Battle and Market as well (the full-lock pattern, not a Train/Work-only lock).

This is the first feature where a player action has an outcome that lands **later**, so the design has to answer where the pending state lives, when it is applied, and what happens to every caller that currently assumes a synchronous result.

### Forces
- **Locked architecture** (CLAUDE.md): all game logic in `app/Services/`, decoupled from Livewire. Duration math is game logic — it lives in the services as constants, not in Blade or JS.
- **ADR-001 §Hospital precedent:** per-row time state is a **lazy timestamp comparison, not a scheduler job**. The regen tick stays the only cron concern.
- **Existing house idiom:** conditional `UPDATE` + affected-count guard for single-row state changes (both current services); `DB::transaction` + `lockForUpdate` only where there are genuinely multiple writes (MarketService, CombatService).
- **The energy economy already paces the game.** Regen is 1 energy / 5 min (`RegenService`, `everyFiveMinutes`). Train costs a flat 5 energy = 25 min of regen. Work costs the whole bar = `max_energy × 5` min. Any timer we add sits *on top of* an existing bottleneck — it must not blindly double it.
- **`max_energy` grows with level** (`EN_PER_LEVEL = 1`), so `max_energy = level + 9`. A Work shift's size, reward and regen cost all already scale with level before we add anything.

### Three forks that need an explicit answer (flagged, not assumed)
1. **Does "busy" block being attacked, or only attacking?** Recommended: **attacking only.** Making a busy character un-attackable turns a Work shift into a 54-minute invulnerability field — the obvious exploit is to stay employed forever. Hospital blocks both directions because it is a *penalty*; busy is a *choice*.
2. **Can you start Train/Work while hospitalized?** Recommended: **yes, unchanged.** ADR-001 §Hospital deliberately left Work and Train ungated ("regen, just can't fight"). The new guard must not quietly reverse that.
3. **What happens if you are hospitalized mid-shift?** Recommended: **nothing** — the shift completes normally. Given fork 1, a busy character can still be attacked and lose. Cancelling or refunding the shift would add a whole rollback path for no design gain.

---

## Decision

### 1. Duration formula

**Linear in level, separate constants per activity**, expressed in seconds:

```
TrainingService::durationFor(int $level): int
    = TRAIN_BASE_SECONDS + TRAIN_SECONDS_PER_LEVEL * ($level - 1)

WorkService::durationFor(int $level): int
    = WORK_BASE_SECONDS  + WORK_SECONDS_PER_LEVEL  * ($level - 1)
```

| Constant | Proposed | Where |
|---|---|---|
| `TRAIN_BASE_SECONDS` | 300 (5 min) | TrainingService |
| `TRAIN_SECONDS_PER_LEVEL` | 30 | TrainingService |
| `WORK_BASE_SECONDS` | 300 (5 min) | WorkService |
| `WORK_SECONDS_PER_LEVEL` | 60 | WorkService |

Level is read **at start** and baked into `activity_completes_at`. It cannot change mid-session anyway — combat is blocked and Work's XP trickle only lands at resolution — so there is no snapshot-vs-live ambiguity to resolve.

#### Pacing at a glance

| Level | Train | Work | `max_energy` | Full-bar refill |
|---|---|---|---|---|
| 1 | **5m 00s** | **5m 00s** | 10 | 50m |
| 5 | 7m 00s | 9m 00s | 14 | 70m |
| 10 | 9m 30s | 14m 00s | 19 | 95m |
| 20 | 14m 30s | 24m 00s | 29 | 145m |
| 50 | 29m 30s | 54m 00s | 59 | 295m |

Both land on the requested **~5 minutes at level 1**.

#### Why these numbers hold up

**Work stays energy-bound, not timer-bound.** A shift's timer is 10–18% of the time it takes to regenerate the energy it consumed (5m/50m at L1 → 54m/295m at L50). The timer therefore adds the "you're at work" state and the lock *without* compounding the bottleneck that already exists. The steady-state income is unchanged by this ADR:

```
gold/hour = (max_energy × rate) ÷ (max_energy × 5 min) = rate × 12
```

— income depends only on the occupation's `gold_per_energy`, **independent of level and of the timer**: 24/h on Grave Digger, 48/h Cursed Courier, 84/h Bone Merchant, 144/h Soul Broker. The timer does not distort the economy.

**Train deliberately crosses over.** Train costs a flat 5 energy = 25 min of regen at every level, so the timer overtakes the energy cost at **level 42** (`300 + 30(L−1) > 1500`). Below that, energy is the constraint and the timer is flavour; above it, session time becomes the real gate on stat accumulation. That crossover is the point of the steeper-than-flat curve — it is the soft cap on late-game stat inflation. If you want the brake to bite earlier, raise `TRAIN_SECONDS_PER_LEVEL`: at 60s/level the crossover moves to level 21.

**Work's per-level slope is double Train's** because a Work session is an all-in energy dump with a reward that already scales twice over (bigger bar × better occupation rate), whereas Train is a fixed 5-energy, fixed +1 transaction. Same base, different slope, two constants each — retunable without touching the formula.

### 2. Session representation and resolution

**Lazy timestamp, no scheduler** — the ADR-001 §Hospital precedent.

One divergence to flag, and it is the whole difficulty of this ADR: **hospital's lazy check is pure, this one has a payload.** `isHospitalized()` just reports `false` once the timestamp passes and nothing needs to happen. A completed training session has to actually *write* the stat gain. So this is "lazy check **plus deferred write**", which brings two requirements hospital never had:

- **Idempotent.** Two concurrent requests must not both apply the gain.
- **Atomic.** The claim and the payout must not interleave.

Both are satisfied by the idiom already in these two services — a conditional `UPDATE` guarded on the activity columns, with an affected-count check:

```php
// TrainingService::resolvePending() — single write, no transaction needed
$affected = Character::whereKey($c->id)
    ->where('activity_type', 'train')
    ->where('activity_completes_at', '<=', now())
    ->update([
        $stat            => DB::raw($stat.' + '.self::STAT_GAIN),
        'activity_type'  => null,
        'activity_stat'  => null,
        'activity_completes_at' => null,
        // ...remaining activity_* columns cleared
    ]);
// $affected === 0 → already resolved, or not due yet. Nothing to do.
```

Work needs gold **and** an `awardXp()` call (which can cascade into a level-up), so its resolution claims the row the same way and then awards XP **inside a `DB::transaction`** — the MarketService pattern — so a crash between the two cannot pay gold and lose the XP. Train needs no transaction; it is one statement.

**The outcome is computed from inputs snapshotted at start**, not re-read at resolution. `activity_energy_spent` and `activity_gold_rate` are written when the session begins, so retuning an occupation — or deleting one — mid-shift cannot change or break a reward the UI already promised.

**Resolution points** (a player never has to click anything for the result to land; they only need the page to repaint):
- top of `TrainingService::start()` and `WorkService::start()`
- `CombatService::resolve()`'s pre-check, and `MarketService::buy/equip/unequip`
- on load in the Dashboard, Work and Train Livewire components

### 3. Where the busy state lives — **nullable columns on `characters`**

```php
Schema::table('characters', function (Blueprint $t) {
    $t->string('activity_type', 16)->nullable();              // 'train' | 'work'
    $t->string('activity_stat', 16)->nullable();              // train: strength|defense|agility
    $t->foreignId('activity_occupation_id')->nullable()
      ->constrained('occupations')->nullOnDelete();           // work: display/provenance only
    $t->unsignedInteger('activity_energy_spent')->nullable(); // snapshot: payout + XP basis
    $t->unsignedInteger('activity_gold_rate')->nullable();    // snapshot of gold_per_energy
    $t->timestamp('activity_completes_at')->nullable();       // the lazy check
});
```

Recommended over a separate `character_activities` table, for four reasons:

1. **The columns enforce the invariant structurally.** The locked blocking rule is "at most one activity per character". Columns make a second concurrent activity *unrepresentable*. A side table needs a partial unique index (which MySQL does not have) or app-level enforcement to say the same thing.
2. **Zero extra queries on the hot path.** `isBusy()`, the status bar and every page load already have the character row in hand. A side table adds a join or a second query to literally every authenticated page render.
3. **Resolution stays one statement.** The idempotent conditional `UPDATE` above touches one table. Across two tables it becomes a mandatory transaction for even the Train case.
4. **It is the established precedent.** `hospitalized_until` is already a nullable timestamp on `characters` for exactly this shape of per-character time state.

The honest cost is **no activity history**, and six nullable columns. Neither is load-bearing: if history is ever wanted, add an append-only `character_activities` log alongside (the `combat_logs` pattern) and keep these columns as the live-state pointer — additive, not a migration of live state. I am explicitly **not** recommending the table just because it is the more "correct" normalisation; nothing in the MVP asks for the extensibility it buys.

Two smaller calls inside this: no index on `activity_completes_at` (every read is `whereKey`-scoped, so it would only serve a sweeper job that this ADR says we are not building), and no `activity_started_at` (nothing needs elapsed time — a countdown only needs the end). Add either later if a progress bar or a sweeper actually appears.

### 4. Busy check + the blocking rule

```php
// Character.php — pure time comparison, same shape as isHospitalized() (ADR-001 blesses this)
public function isBusy(): bool
{
    return $this->activity_completes_at !== null && $this->activity_completes_at->isFuture();
}
```

Note the ordering that makes this self-healing: a caller **resolves first, then checks `isBusy()`**. Once `completes_at` passes, `isBusy()` is already false while `activity_type` is still set, so the resolve call clears it and the action proceeds in the same request.

Blocked while busy, each with in-character copy in the V1 flavour-text idiom (arrays already established in `train.blade.php` / `work.blade.php`), not a raw error:

| Action | Blocked | Message home |
|---|---|---|
| Train (start) | yes | TrainingService |
| Work (start) | yes | WorkService |
| Battle (as attacker) | yes | CombatService, in `assertCanFight()` |
| Battle (as defender) | **no** — fork 1 | — |
| Market buy/equip/unequip | yes | MarketService |

### 5. New method shape and what happens to today's callers

```php
final class ActivityService                       // the one seam — dispatches on activity_type
{
    public function resolvePending(Character $c): ?ActivityResult;
}

class TrainingService
{
    public static function durationFor(int $level): int;              // pure, directly testable
    public function start(Character $c, string $stat): ActivityResult;  // validates + deducts energy + records session
    public function resolvePending(Character $c): ?ActivityResult;      // applies the stat gain, once
}

class WorkService                                  // same shape: durationFor / start / resolvePending
```

`ActivityService` exists so no caller has to know *which* activity is pending — Combat, Market and the three page components all call one method, the way `LevelingService::awardXp()` is the single XP seam in ADR-001. `Character::isBusy()` stays on the model (pure timestamp, no math).

**Migration path for the existing callers and tests:**

| Today | After |
|---|---|
| `TrainingService::train()` | split → `start()` + `resolvePending()`; `ENERGY_COST` / `STAT_GAIN` / the stat whitelist all unchanged |
| `WorkService::work()` | split the same way; `XP_PER_ENERGY` unchanged, but the trickle now fires **at resolution**, not at start |
| `Train.php` / `Work.php` components | call `start()`; flash "you begin…" instead of the outcome; resolve on load |
| `Battle.php`, `Market.php` | no signature change — the new guard lives inside the services they already call |
| **Assertions that survive as-is**, just moved onto `start()` | energy deducted; energy-boundary accept/reject; occupation level gate; the stat-whitelist column-injection guard; "no energy to work" |
| **Assertions that move behind `travel()`** | stat actually raised; gold earned; XP trickle and any level-up |
| **New tests** | nothing applied before `completes_at`; applied once time passes and the character is next touched; second `start()` while busy throws; Battle and Market rejected while busy; everything unblocks after resolution; `durationFor()` matches the table above at levels 1/5/10/20/50 |

`RegenServiceTest`, `CombatServiceTest`, `LevelingServiceTest`, `HospitalTest` and `MarketServiceTest` need no change beyond adding the new busy-guard cases to the latter two — no combat, hospital or leveling behaviour moves.

---

## Options Considered

### Duration curve

| Option | L1 | L5 | L10 | L20 | L50 | Verdict |
|---|---|---|---|---|---|---|
| **Linear — `BASE + PER_LEVEL×(L−1)`** | 5m | 7m | 9m30 | 14m30 | 29m30 | **Chosen** — two integer constants, gentle for a new player, retunable by inspection |
| `BASE × √L` | 5m | 11m11 | 15m49 | 22m22 | 35m21 | Rejected — front-loads the pain (L5 already doubles L1) and trades a readable constant for a curve nobody can retune by eye |
| `BASE × L` | 5m | 25m | 50m | 100m | 250m | Rejected outright — 4 hours per stat point at L50 |
| Flat 5m at every level | 5m | 5m | 5m | 5m | 5m | Rejected — satisfies "5 minutes at level 1" but not "scales with level"; no brake on late-game stat inflation |

Also considered and rejected for Work: **duration proportional to energy actually spent** (`WORK_BASE + PER_ENERGY × energy_spent`). It is arguably the most honest model — a bigger shift *should* take longer — and it lands on 5m at L1 with a full bar. Rejected because the brief specifies a function of level, and because it makes the displayed duration depend on how full your bar happened to be, which is harder for a player to reason about. Worth revisiting if partial-energy shifts ever feel wrong.

### Storage shape

| Option | Complexity | Query cost | Invariant | Verdict |
|---|---|---|---|---|
| **Nullable columns on `characters`** | Low | Free (row already loaded) | Structural | **Chosen** |
| `character_activities` table | Med | +1 query/join per page | Needs app-level or partial-index enforcement | Rejected for MVP; add later as an append-only *log* if history is wanted |

### Resolution trigger

| Option | Verdict |
|---|---|
| **Lazy check at every relevant service call + page load** | **Chosen** — ADR-001 §Hospital precedent, no new infra |
| Scheduler sweep (`Schedule::command('game:resolve-activities')`) | Rejected — pure waste; the payload only matters when someone looks at it. Reconsider only if a future feature needs an activity to complete with *nobody* observing it (e.g. an email or push on completion — explicitly out of scope) |
| Queued job dispatched with `->delay($completesAt)` | Rejected — adds a worker as a hard runtime dependency for a timestamp comparison, and a lost job silently eats a player's session |

---

## Trade-off Analysis

- **`ActivityService` as a dispatcher vs. each caller branching on `activity_type`:** chose the seam. Five call sites will need resolution; four of them (Combat, Market, and the page components) have no business knowing Train from Work. Same reasoning that made `awardXp()` a seam in ADR-001.
- **`isBusy()` on the model vs. in a service:** on the model, matching `isHospitalized()`. It is a timestamp comparison, not game math — the CLAUDE.md rule is about *calculation* logic, and ADR-001 already set this precedent.
- **Snapshotting `gold_rate`/`energy_spent` vs. re-reading the occupation at resolution:** snapshot. Two extra small columns buy immunity to occupation retuning and deletion mid-shift, and remove a query from the resolution path. The FK is kept for display only.
- **Blocking Market as well as Battle:** locked by the brief, and it is the right call — without it, "start a 54-minute shift, then go shopping and fighting" makes the lock cosmetic. The cost is that four service methods gain a guard.
- **Energy debited at start, reward paid at resolution:** the alternative (debit at resolution) would let a player start a session they cannot afford by the time it lands. Debit-first also makes an abandoned session self-punishing rather than free.

## Consequences

**Easier:** the "you're at work" state V1 had, with no scheduler and no queue worker; one seam (`ActivityService`) for every consumer; duration is four integer constants, retunable without touching logic; the countdown UI needs only a server-rendered ISO timestamp.

**Harder:** Train and Work are no longer synchronous, so both test files are rewritten around `travel()`; four services gain a busy guard; the Work XP trickle (and therefore level-ups from Work) now fires at resolution, which shifts *when* a level-up is observed — not the math; six nullable columns land on the hottest table in the schema.

**Revisit post-MVP:** activity history (`character_activities` as an append-only log); a duration cap if levels ever climb far enough for linear growth to bite (level 200 Work would be ~3.4h — not reachable at ADR-001's XP curve, so not built); an "abandon session" action that forfeits the reward; energy-proportional Work duration; a sweeper if completion ever needs to fire unobserved.

---

## Tunables (sign off or adjust)

| Constant | Proposed | Where | Effect |
|---|---|---|---|
| `TRAIN_BASE_SECONDS` | 300 | TrainingService | Level-1 train duration |
| `TRAIN_SECONDS_PER_LEVEL` | 30 | TrainingService | Train growth; sets the energy→timer crossover (L42 at 30s; L21 at 60s) |
| `WORK_BASE_SECONDS` | 300 | WorkService | Level-1 shift duration |
| `WORK_SECONDS_PER_LEVEL` | 60 | WorkService | Shift growth; keep below the refill window to stay energy-bound |

Unchanged and out of scope: every ADR-001 tunable, `ENERGY_COST` (5), `STAT_GAIN` (1), `XP_PER_ENERGY` (1), `RegenService`'s tick values and cadence.

## Action Items

1. [ ] Owner signs off on §Tunables **and the three flagged forks** (busy blocks attacking only; Train/Work stay available while hospitalized; hospitalization mid-shift changes nothing) → Status: Accepted.
2. [ ] Migration: the six `activity_*` columns on `characters`, `Character::isBusy()`, datetime cast for `activity_completes_at`.
3. [ ] `TrainingService` / `WorkService` → `durationFor()` + `start()` + `resolvePending()`; `ActivityService` seam.
4. [ ] Busy guards in `CombatService::assertCanFight()` and `MarketService::buy/equip/unequip`, with V1-idiom flavour copy.
5. [ ] Resolve-on-load in the Dashboard, Work and Train components; countdown badge on Work/Train and in the status bar (Alpine formatting a server-provided ISO timestamp — presentational only, server stays the source of truth).
6. [ ] Rewrite `TrainingServiceTest` / `WorkServiceTest` per the migration table; add busy-guard cases to `CombatServiceTest` and `MarketServiceTest`.
7. [ ] Update `README.md`'s "how to play" for the new pacing.
