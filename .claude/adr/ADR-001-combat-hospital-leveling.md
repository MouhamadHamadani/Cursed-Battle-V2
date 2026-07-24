# ADR-001: Combat Resolution, Hospitalization & Leveling (Phases 6–8)

**Status:** Proposed
**Date:** 2026-07-24
**Deciders:** Mouhamad (project owner) — sign-off needed on the tunable constants (§Tunables) and the two forks already chosen below.
**Supersedes:** the draft combat formula in CLAUDE.md (which explicitly says "formalize via ADR before implementing Phase 6").

---

## Context

Phases 0–5 are shipped: characters with fixed stat columns (strength/defense/agility), health/max_health + scheduler regen, gold/energy economy (Work/Train/Market), and equippable items carrying signed stat deltas. Phase 5 deliberately **deferred effective-stat aggregation** (base + equipped gear) to here, because combat is its first real consumer.

CLAUDE.md carries a **draft, non-final** combat formula and three interlocked features to design as one unit:
- **Phase 6 — Combat:** `CombatService::resolve(Character $attacker, Character $defender): CombatResult`, instant-resolve, a single synchronous bounded loop (never `while(true)`, never real-time timers), + a `combat_logs` table.
- **Phase 7 — Hospital:** losing a fight puts you on a cooldown; `resolve()` must reject attacks if either party is hospitalized.
- **Phase 8 — Leveling:** accumulate XP → level up.

They are one loop: a fight drains health, sets the loser's hospitalization, moves gold, and awards the winner XP toward a level-up. Designing them separately would fragment the same transaction.

### Forces
- **Locked stack/model** (CLAUDE.md, non-negotiable): three fixed stat columns — **no new "speed" stat**; all game logic in `app/Services/`, decoupled from Livewire; MySQL/InnoDB; instant-resolve only.
- **Genre reference** (Torn/OpenDominion): attack resolves against stored stats; health is a life pool consumed by fighting and regenerated over time; losers are hospitalized.
- **Security spine** (established Phases 3–5): any public Livewire method is callable from the browser with untrusted args — the service re-validates everything and every multi-write is atomic.
- **Reality:** solo passion/portfolio project, likely few concurrent players early → progression can't depend solely on live opponents.

### Two forks resolved with the owner (2026-07-24)
1. **Health = persistent HP pool** (Torn-like). Fights start at *current* health, damage drains both fighters' stored health and persists, loser is hospitalized. This makes the already-built health + health-regen load-bearing (regen is the natural rate-limit on attacking).
2. **XP = combat win + a small Work trickle.** Winning fights is the primary XP source; Work adds a small XP trickle so a solo player with no opponents can still level. Train awards no XP (it already grants a permanent stat — no double-dip).

---

## Decision

Build Phases 6–8 as one cohesive combat-and-consequences system. `CombatService::resolve()` runs a bounded, deterministic-per-roll simulation over **effective** stats, then persists all outcomes (both healths, loser's hospitalization, gold transfer, winner's XP) and the immutable `combat_logs` row inside **one `DB::transaction` with both character rows locked**. Hospitalization is a **lazy time check** (`hospitalized_until` in the future), NOT a scheduler concern. Leveling lives behind a single `LevelingService::awardXp()` seam that both combat and Work call.

### Effective stats (resolves the Phase 5 deferral)
```
effective_strength = base.strength + Σ(equipped items' strength_delta)   // same for defense, agility
```
Home: **`CombatService::effectiveStats(Character): array{strength,defense,agility}`** (public, so the Dashboard/Market can display effective stats without duplicating the math). Eager-load equipped `character_items.item` to avoid N+1. This is the ONE aggregation point — combat and display share it; nothing recomputes gear deltas independently.

### The `resolve()` algorithm (this replaces the CLAUDE.md draft)

**Pre-checks** (throw `GameActionException` — reuse the Phases 3–5 class — on any failure; the Livewire component catches → flashes):
1. `attacker->id !== defender->id` (no self-attack)
2. `!attacker->isHospitalized()` (you're in hospital)
3. `!defender->isHospitalized()` (target is protected while hospitalized)
4. `attacker->health > 0` (must have life to fight; regen first)

**Atomic body** — `DB::transaction`, lock BOTH character rows with `lockForUpdate()` **in ascending id order** (lock `min(id)` then `max(id)`; prevents deadlock when A→B and B→A fire concurrently). Re-load fresh under lock and re-run pre-checks (hospitalization/health may have changed):

```
atkHP = attacker.health ; defHP = defender.health           // persistent HP pool: start at CURRENT health
atk = effectiveStats(attacker) ; def = effectiveStats(defender)
order = effective-agility desc; tie → attacker acts first
events = []
for round in 1..MAX_ROUNDS (10):
    for (actor, target, actorHP&targetHP) in order:
        dodge% = min(target.effective_agility / 2, DODGE_CAP 75)          // HARD CAP 75 (draft's fixed bug)
        if rand(1,100) <= dodge%:
            events[] = {round, actor, dodged:true, damage:0, target_hp:targetHP}
            continue
        base   = actor.effective_strength - target.effective_defense
        swing  = rand(-actor.level, +actor.level)                          // ± level variance (draft)
        damage = max(MIN_DAMAGE 1, base + swing)                           // floor 1 so HP always moves
        targetHP -= damage
        events[] = {round, actor, dodged:false, damage, target_hp: max(0,targetHP)}
        if targetHP <= 0: winner = actor; break out of both loops (KO)
    if KO: break

if no KO after 10 rounds:
    winner = higher remaining HP; exact tie → DEFENDER wins    // attacker failed to break them; no true draw in MVP
```
- **Termination guaranteed** by the 10-round × 2-swing cap (≤20 swings) regardless of damage — satisfies "never `while(true)`". `MIN_DAMAGE=1` keeps HP moving but the round cap is the hard bound.

**Persist (same transaction):**
- `attacker.health = max(0, atkHP)`, `defender.health = max(0, defHP)`.
- **Loser** (whether KO or HP-tiebreak): `hospitalized_until = now() + HOSPITAL_MINUTES`. Health left at its drained value (0 if KO'd).
- **Gold:** `stolen = floor(loser.gold * GOLD_STEAL_PCT 0.10)`; `loser.gold -= stolen`, `winner.gold += stolen`.
- **XP:** `winner` gains `xp_win = XP_BASE 50 + loser.level * XP_PER_LEVEL 10`, **halved** if `winner.level > loser.level + FARM_GAP 5` (anti-farming). Via `LevelingService::awardXp(winner, xp_win)`. Loser gains 0.
- **Write `combat_logs`** (immutable): attacker/defender ids + levels, effective-stat + starting-HP JSON snapshots, round-by-round `events` JSON, `winner_id`, `gold_change` & `xp_change` **from the attacker's perspective** (attacker is the log's actor: gold_change +stolen if attacker won else −stolen; xp_change = attacker's xp gained, 0 if attacker lost).

**Return** `CombatResult` (readonly VO): winner, loser, attacker, defender, `events[]`, `goldChange`, `xpChange`, `leveledUp`, `rounds`, `knockout`. The **component does not persist anything** — it renders the result; `resolve()` already wrote everything.

### `combat_logs` migration (Phase 6)
```php
Schema::create('combat_logs', function (Blueprint $t) {
    $t->id();
    $t->foreignId('attacker_id')->constrained('characters')->cascadeOnDelete();
    $t->foreignId('defender_id')->constrained('characters')->cascadeOnDelete();
    $t->unsignedInteger('attacker_level');
    $t->unsignedInteger('defender_level');
    $t->json('attacker_stats');   // effective stats + starting HP snapshot
    $t->json('defender_stats');
    $t->json('events');           // round-by-round
    $t->foreignId('winner_id')->nullable()->constrained('characters')->nullOnDelete();
    $t->integer('gold_change');   // attacker's perspective (signed)
    $t->integer('xp_change');     // attacker's perspective
    $t->timestamp('created_at')->useCurrent();   // append-only; no updated_at (CLAUDE.md lists created_at only)
});
```
Model `CombatLog`: `$guarded=[]`, `casts()` → `['attacker_stats'=>'array','defender_stats'=>'array','events'=>'array']`, `attacker()/defender()/winner()` BelongsTo. **cascadeOnDelete** on the fighters (logs die with a deleted character — MVP; note the nullOnDelete+history-preservation alternative under Consequences).

### Hospital (Phase 7)
- `Character::isHospitalized(): bool` → `hospitalized_until !== null && hospitalized_until->isFuture()` (uses the existing datetime cast). Introduced in Phase 6 for the combat pre-check, **owned/documented here.**
- **Lazy time check, no scheduler.** Release is implicit — when `hospitalized_until` passes, `isHospitalized()` returns false. This is the one place a lazy-timestamp check is correct; it does NOT contradict the "regen via scheduler" lock (regen is a global tick; hospital release is a per-row time comparison). No cron, no job.
- **Scope: hospital blocks combat only** (both directions — can't attack while hospitalized, can't be attacked while hospitalized). **Work and Train stay available** while hospitalized — localizes the guard to `CombatService` exactly as CLAUDE.md specifies, matches the existing RegenService comment ("regen, just can't fight"), and is less punishing for a low-population game.
- Phase 7 deliverables: the `isHospitalized()` helper, a hospital-status banner/component (dashboard + a `/hospital` page showing remaining minutes), and confirming Work/Train are intentionally NOT gated. No pay-to-heal (post-MVP).

### Leveling (Phase 8)
- **`LevelingService::awardXp(Character $c, int $xp): LevelUpResult`** — the single XP seam. **Born in Phase 6** doing xp-increment only (so combat compiles and records xp_change); **enriched in Phase 8** with the level-up loop *inside the same method* — no combat call-site change.
- Level-up loop (bounded, terminates — xp strictly decreases each iteration):
  ```
  c.xp += xp
  while (c.xp >= threshold(c.level)):        // threshold(L) = L * XP_PER_LEVEL_STEP 100
      c.xp -= threshold(c.level)
      c.level += 1
      c.max_health += HP_PER_LEVEL 10 ; c.max_energy += EN_PER_LEVEL 1
  if leveled: c.health = c.max_health ; c.energy = c.max_energy   // heal+refill reward on level-up
  ```
  `characters.xp` = **progress toward next level** (resets on level-up), not lifetime total. Stats stay training-driven (no auto stat gain on level-up — level raises pools + gates content).
- **Wire Work's trickle (Phase 8):** `WorkService` calls `awardXp(character, energy_spent * XP_PER_ENERGY 1)` — a 20-energy shift = 20 XP. Combat win (≈50–100+) dwarfs it, so PvP stays primary. This is the deferred one-line addition to WorkService.
- Runs inside the same transaction as its caller (combat's, or work's) so XP + level-up + heal commit atomically.

### UI (Phase 6)
- `/battle` (or `/attack`) full-page Livewire component: lists attackable characters (others, `!isHospitalized()`), Attack button → `resolve()` → renders the `CombatResult` round-by-round + outcome (winner, damage, gold/xp, "Leveled up!"). No matchmaking — a simple opponent list for MVP.
- Optional: a `/combat-logs` history list (your fights). CLAUDE.md's `combat_logs` dropped the old prototype's `read` flag, so **no unread-notification system** — a plain history list only, if built at all this phase.

---

## Options Considered (the combat core)

### Option A: Persistent HP pool, effective-stats sim, atomic resolve — **CHOSEN**
| Dimension | Assessment |
|-----------|------------|
| Complexity | Med — one bounded loop, one locked transaction |
| Cost | Low — no new infra; reuses transaction+lock pattern from MarketService |
| Scalability | Fine — instant-resolve, O(rounds) per fight, no live state |
| Team familiarity | High — same service/atomicity idioms as Phases 3–5 |

**Pros:** makes health+regen meaningful; matches genre; self-rate-limiting via health; one transaction keeps all consequences consistent. **Cons:** attacker health drain can frustrate ("can't attack, low health") — mitigated by regen; balance needs tuning (the §Tunables knobs exist for exactly this).

### Option B: Snapshot simulation (fight from max_health, only loser's consequences persist)
**Pros:** simplest; attacker never "too hurt to fight." **Cons:** health column + its regen become near-cosmetic (only gate hospitalization); diverges from genre; **rejected** — wastes the already-built regen system and the owner chose A.

### Option C: Real-time / multi-round-with-timers combat
**Rejected outright** — violates CLAUDE.md's instant-resolve lock and the "never `while(true)`/no real-time timers" rule. Listed only to record it was considered and excluded by constraint.

---

## Trade-off Analysis
- **Effective-stats home (CombatService method vs a new StatsService vs model accessors):** chose a public `CombatService::effectiveStats()` — combat is the primary consumer, display can reuse it, one file (ponytail). Extract to a `StatsService` only if a third consumer with different needs appears.
- **XP seam built in Phase 6 vs Phase 8:** define `LevelingService::awardXp` in Phase 6 (increment-only) and enrich in Phase 8 — the alternative (combat does a raw `increment('xp')`, Phase 8 rewrites call sites) churns more code. One seam, progressively filled.
- **Hospital lazy-check vs scheduler release:** lazy time comparison is strictly simpler and correct; a scheduler "release" job would be pure waste. This is deliberately different from regen (which the genre wants as a visible global tick) and does not reopen that decision.
- **Log FK cascade vs nullOnDelete:** cascade (logs die with the character) for MVP simplicity; fighter FKs cascade, `winner_id` is nullOnDelete+nullable. Preserving history past account deletion is a post-MVP change (nullable attacker/defender + nullOnDelete + denormalized names).

## Consequences
**Easier:** health/regen now has purpose; a single `resolve()` is the whole PvP surface; effective-stats computed once; leveling has one seam; hospital needs no infra.
**Harder:** balance tuning (many knobs — but all isolated as constants); combat is the first place lock-ordering matters (documented: ascending id); `combat_logs` JSON snapshots must be written on every fight (immutable, fine).
**Revisit post-MVP:** level-range attack restrictions / respect system; pay-to-heal hospital; energy (or "nerve") cost per attack; XP from Train; combat-log history preservation across account deletion; scaling hospital time by damage taken; per-account concurrency beyond row locks if throughput ever matters.

## Tunables (all constants in their service; sign off or adjust)
| Constant | Default | Where |
|---|---|---|
| `MAX_ROUNDS` | 10 | CombatService |
| `DODGE_CAP` | 75 (%) | CombatService |
| `MIN_DAMAGE` | 1 | CombatService |
| `GOLD_STEAL_PCT` | 0.10 | CombatService |
| `HOSPITAL_MINUTES` | 30 | CombatService (sets loser's cooldown) |
| `XP_BASE` / `XP_PER_LEVEL` / `FARM_GAP` | 50 / 10 / 5 | CombatService (win XP) |
| `threshold(L)` step | L × 100 | LevelingService |
| `HP_PER_LEVEL` / `EN_PER_LEVEL` | 10 / 1 | LevelingService |
| `XP_PER_ENERGY` (Work trickle) | 1 | WorkService (Phase 8) |

## Action Items
1. [ ] Owner signs off on §Tunables (or adjusts) and this ADR → Status: Accepted.
2. [ ] `/make-plan` for Phases 6–8 against this ADR (with a Doc-Discovery pass on L12 JSON casts, `lockForUpdate` ordering, `rand()` in services + how to make combat tests deterministic — likely a seeded/injectable RNG so Pest can assert exact rounds).
3. [ ] Phase 6: `combat_logs` + `CombatLog` model + `CombatService::{effectiveStats,resolve}` + `CombatResult` VO + `LevelingService::awardXp` (increment-only) + `Character::isHospitalized()` + `/battle` UI. Tests: dodge cap, min-damage floor, KO vs 10-round-tiebreak, gold transfer, atomic rollback, **deterministic RNG** so round-by-round is assertable.
4. [ ] Phase 7: hospital banner + `/hospital` page; confirm Work/Train ungated; test the both-directions combat block.
5. [ ] Phase 8: enrich `awardXp` with the level-up loop (pool increases + heal) + wire Work's XP trickle. Tests: single/multi level-up from one award, xp carryover, heal-on-levelup.
6. [ ] Update CLAUDE.md: mark the draft combat formula superseded by ADR-001; note the `jobs`→`occupations` rename already made.

## RNG note (flagged for the plan, not decided here)
`rand()` inside CombatService makes fights non-deterministic → hard to unit-test round-by-round. The plan should inject an RNG (a `RandomGenerator` interface or a simple closure/seed passed to `resolve()`), defaulting to real randomness in prod and a seeded/scripted sequence in tests. This is a testability seam, not a game-design decision — resolve it in `/make-plan`, not here.
