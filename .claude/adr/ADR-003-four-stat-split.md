# ADR-003: Four Battle Stats — Splitting Agility into Speed & Dexterity

**Status:** Accepted (2026-09-01) — the stat split itself is decided by the owner and is not a proposal. The **mechanic** built on top of it (the miss roll and its two constants) ships at the proposed defaults in §Tunables and is flagged there for sign-off or adjustment, the same way every ADR-001/002 tunable was.
**Date:** 2026-09-01
**Deciders:** Mouhamad (project owner) — the four-stat split (strength / defense / speed / dexterity) was decided directly; the miss formula below is this ADR's proposal against that decision.
**Supersedes:** one line of [ADR-001](ADR-001-combat-hospital-leveling.md) §Forces — "three fixed stat columns — no new 'speed' stat". Nothing else in ADR-001 moves: hospital, leveling, gold-steal and the XP formulas are untouched. ADR-001 carries a dated addendum note pointing here.
**Related:** [ADR-002](ADR-002-timed-sessions.md) — the trainable-stat whitelist and `activity_stat` column widen from three names to four; the session model itself is untouched.

---

## Context

Characters carry three fixed stat columns: `strength`, `defense`, `agility`. `CombatService::resolveTurn()` uses them like this:

1. **dodge** — `effectiveDodgeChance(agility)` = `min(agility ÷ 2, 75)`. If the roll lands under it, the swing deals nothing.
2. **damage** — `max(MIN_DAMAGE, strength − defense ± rand(level))`.
3. **turn order** — higher effective agility acts first; exact tie → attacker.

Every swing that is not dodged **lands**. There is no accuracy stat anywhere in the model: a fighter's only way to avoid a blow is the defender-side dodge roll, and an attacker has no stat that makes their own swings connect more reliably. That is a real gap in the combat model, not a cosmetic one — it means offence has exactly one lever (raw strength) while defence has two (defense, agility).

Torn is this project's explicit genre reference (ADR-001 §Forces). Torn runs **four** battle stats — Strength, Defense, Speed, Dexterity — where **Speed governs hit chance** and **Dexterity governs dodge chance**. That split is exactly the shape of the gap above. For contrast: Kingdom of Loathing runs three stats, Mafia Wars runs two (Attack/Defense only) — neither models accuracy at all, and neither is the reference this project chose.

So: split `agility` into `dexterity` (keeps today's dodge role, unchanged) and `speed` (new: governs hit chance). This imports Torn's stat *shape* without importing Torn's escape/flee mechanics, which do not apply here — combat is instant-resolve over bounded rounds and no flee action exists or is planned.

### Forces
- **ADR-001 §Forces said "no new speed stat."** This ADR reverses precisely that clause, on the owner's decision, and nothing adjacent to it. The line stays in ADR-001's historical text with a dated addendum pointing here — ADR-001 is a record, not a living document.
- **CLAUDE.md's stats rationale still holds.** "Fixed stat columns, not a skills-pivot table … three stats don't justify the join overhead." Four stats don't either. This is a two-column-wide schema change, not a re-architecture, and it does **not** reopen the skills-pivot decision.
- **One aggregation point.** ADR-001 made `CombatService::effectiveStats()` the single place base stats and equipped-gear deltas are summed. Four stats means the returned array and the `items` delta columns widen together — no second aggregation appears.
- **Untrusted client input.** `TrainingService`'s stat whitelist is a column-injection guard (`$stat` is interpolated into SQL). It widens to four names; it does not loosen.
- **`combat_logs` is immutable and append-only.** Rows already written carry `agility` in their JSON snapshots and stay exactly as they are — historical records of a fight fought under the three-stat model. New rows carry `speed`/`dexterity`. Nothing reads these snapshots programmatically, so no reader needs a compatibility branch.
- **Determinism in tests.** `CombatService` takes a constructor-injected `\Random\Randomizer`; the suite seeds `Mt19937` and asserts exact round-by-round outcomes. A new RNG draw per swing would shift every seeded sequence in the file. This constrains the implementation (see §Decision, the zero-chance short-circuit).

---

## Decision

### 1. Schema

`characters`: `agility` → **`speed`** + **`dexterity`**, both `unsignedInteger` default **5** (the same default `agility` carried, so a new character starts 5/5/5/5).

`items`: `agility_delta` → **`speed_delta`** + **`dexterity_delta`**, both signed `integer` default **0**.

Nothing else changes shape. `combat_logs` needs no migration — its stat snapshots are JSON.

### 2. The mechanic — **proposed defaults, owner-adjustable**

**Dodge is unchanged in every respect except which column feeds it.**

```php
CombatService::effectiveDodgeChance(int $dexterity): int
    = min(intdiv($dexterity, 2), DODGE_CAP);       // DODGE_CAP stays 75
```

**Miss is new** — an opposed roll on the *speed differential*, not on absolute speed. A fighter is hard to hit because they are faster **than you**, not because they are fast:

```php
CombatService::effectiveMissChance(int $attackerSpeed, int $defenderSpeed): int
    = min(intdiv(max(0, $defenderSpeed - $attackerSpeed), 4), MISS_CAP);   // MISS_CAP proposed 40
```

`max(0, …)` means a faster-or-equal attacker **never misses** — the floor is 0%, so speed is purely a contest and parity costs nothing. Proposed divisor **4**, proposed `MISS_CAP` **40**.

| Speed differential (defender − attacker) | Miss chance |
|---|---|
| ≤ 0 (attacker faster or equal) | **0%** |
| 4 | 1% |
| 20 | 5% |
| 40 | 10% |
| 100 | 25% |
| 160 | **40% (cap)** |
| 400 | 40% (cap) |

**Round order inside a swing — miss first, then dodge:**

```
missChance = effectiveMissChance(actor.speed, target.speed)
if missChance > 0 and roll(1,100) <= missChance:
    → event{missed: true, dodged: false, damage: 0}      # a distinct log line; dodge is NOT rolled
dodgeChance = effectiveDodgeChance(target.dexterity)
if roll(1,100) <= dodgeChance:
    → event{missed: false, dodged: true, damage: 0}      # today's line, verbatim
→ damage as today
```

The two are **semantically distinct and rendered distinctly**: a miss is the attacker's failure ("thy blow finds only air"), a dodge is the defender's success ("slips the blow"). Each round event carries both `missed` and `dodged` booleans so the JSON shape is uniform across every event.

**Worst case is bounded and deliberate:** 40% miss × 75% dodge = **85%** of swings can whiff against a maximally invested defender, up from 75% today. `MIN_DAMAGE = 1` and the `MAX_ROUNDS = 10` cap still guarantee termination and a decided outcome, so this cannot stall a fight — it only makes a speed/dexterity build harder to hurt. If 85% reads as too high in play, `MISS_CAP` is the one knob to turn.

**The `missChance > 0` short-circuit is load-bearing, not an optimisation.** A 0% chance can never fire, so skipping the draw is semantically identical — and it keeps the RNG stream byte-identical for every fight between equal-speed characters, which is every existing seeded test. Without it, adding this feature would silently re-roll every assertion in `CombatServiceTest`. The dodge roll is deliberately **not** given the same guard: changing when *it* draws would shift the streams the existing tests were written against.

### 3. Turn order moves to speed

Turn order was "higher effective **agility** acts first, exact tie → attacker." Agility no longer exists, so this has to be re-homed, and **speed** is the only honest choice: acting first is a question of who is quicker, not of who is harder to hit. Dexterity stays purely defensive — dodge and nothing else. Tie-break rule unchanged (attacker acts first).

This means speed does two jobs (accuracy + initiative) and dexterity one (evasion). That asymmetry is intentional: it gives speed a reason to exist for a pure-offence build, which a hit-chance-only stat would not.

### 4. Trainable stats

`TrainingService::STATS` widens to `['strength', 'defense', 'speed', 'dexterity']`. Cost (`ENERGY_COST` 5), gain (`STAT_GAIN` 1) and session duration are untouched — a fourth stat means the same energy buys progress in one of four directions instead of one of three, which slows *specialisation* slightly and is the intended cost of the extra depth. `activity_stat` (varchar 16) already fits `dexterity`. The Train page grows a fourth card.

### 5. Data migration — **backfill, not drop-and-recreate**

The dev DB was inspected before choosing: **6 characters, all sitting at the default `agility` 5**, and **8 items whose `agility_delta` values match `ItemSeeder` exactly** (nothing hand-tuned). By the letter of the brief that is disposable and a clean recreate would have been allowed.

**Backfill was chosen anyway**, because it is three extra SQL statements and removes the question entirely — no reseed, no "was anything hand-edited since?", and it is the migration that would be *correct* on a populated database, which is the one that matters if this ever runs anywhere but this laptop.

```
characters:  add speed, dexterity  →  UPDATE SET speed = agility, dexterity = agility  →  drop agility
items:       add speed_delta, dexterity_delta
             →  UPDATE SET speed_delta = agility_delta, dexterity_delta = agility_delta
             →  drop agility_delta
```

Both new columns take the **same** old value rather than splitting it — a character with 5 agility becomes 5 speed *and* 5 dexterity. Halving it would silently nerf every existing character; duplicating it is a free, obviously-correct grant at this population size. `down()` is symmetric (re-add `agility` from `speed`, drop both).

### 6. Out of scope — **still deferred**

**Durability, weight, and any repair/decay mechanic: still deferred.** No columns, no decay logic, no repair UI. (V1's durability was buggy anyway — inverted wear calculation, gated on a hardcoded category-name check — and is not worth porting.)

---

## Options Considered

### How many stats

| Option | Precedent | Verdict |
|---|---|---|
| **4 — STR / DEF / SPD / DEX** | **Torn** (this project's stated genre reference) | **Chosen** — closes the accuracy gap using the reference's own split |
| 3 — keep agility as-is | Kingdom of Loathing | Rejected — leaves offence with one lever and defence with two; no accuracy model at all |
| 2 — Attack / Defense | Mafia Wars | Rejected — strictly less depth than what already ships |
| 5+ — add e.g. luck/endurance | — | Rejected — nothing in the MVP asks for it; four already stretches the "fixed columns" justification |

### Miss formula

| Option | Verdict |
|---|---|
| **Opposed differential — `min((defSpd − atkSpd) ÷ 4, cap)`, floored at 0** | **Chosen** — parity is free, speed only matters relative to who you fight, mirrors the shape of `strength − defense` |
| Absolute — `min(defenderSpeed ÷ 2, cap)`, dodge's exact shape | Rejected — makes speed a second dodge stat and gives an attacker's own speed no effect whatsoever, which is the gap this ADR exists to close |
| Ratio — `atkSpd ÷ (atkSpd + defSpd)` | Rejected — smoother, but not retunable by eye and it makes low-level fights (5 vs 5 → 50%) behave nothing like high-level ones |

### Where the miss roll sits

| Option | Verdict |
|---|---|
| **Miss first, dodge only if the swing connects** | **Chosen** — reads correctly ("you swung badly" precedes "they got out of the way") and a missed swing costs the defender no dodge roll |
| Dodge first, then miss | Rejected — a defender "dodging" a swing that was never going to land is incoherent in the log |
| One combined roll | Rejected — collapses two stats into one number and makes the distinct log line impossible |

### Migration shape

| Option | Verdict |
|---|---|
| **Add → backfill → drop** | **Chosen** — correct on any database, not just this one |
| Drop `agility`, add two fresh columns, re-run seeders | Allowed by the brief (the dev data is disposable) but rejected — same file size, worse on any populated DB |
| Keep `agility` as a deprecated column alongside | Rejected outright — two sources of truth for one concept is exactly the bug this split is meant to avoid |

---

## Trade-off Analysis

- **Duplicating agility into both columns vs. splitting it:** duplicate. Splitting (e.g. `speed = ceil(agility/2)`) would silently take stats away from every existing character to satisfy a tidiness that nobody asked for. Six characters at 5/5 makes this a non-decision in practice; the principle is what is being recorded.
- **Speed owning turn order as well as accuracy:** yes, and deliberately. Had dexterity kept turn order, speed would be a single-purpose stat and dexterity a triple-purpose one (dodge + initiative), which inverts the asymmetry in exactly the wrong direction.
- **`MISS_CAP` 40 vs. matching `DODGE_CAP` 75:** 40. The two caps stack multiplicatively, and 75/75 would mean 94% of swings whiff against a specialist — fights decided almost entirely by the round cap and the HP tiebreak. 40 keeps the stacked worst case at 85%, which is a meaningful step up from today's 75% without turning combat into a coin-flip. This is the number most likely to want tuning after real play.
- **Widening the trainable whitelist vs. adding a second Train page:** widen. The whitelist is a security guard, not a UI concern, and the Train page is a `@foreach` over a stat array — a fourth entry is a data change, not a code change.
- **Not touching ADR-001's superseded line in place:** an ADR is a dated record of what was decided and why. Editing the old line to say the opposite would destroy the audit trail that makes ADRs worth keeping. A dated addendum pointing here preserves both.

## Consequences

**Easier:** offence has two levers instead of one, so gear and training choices actually branch; a "fast" build is expressible for the first time; the miss/dodge split gives the battle log two genuinely different lines to tell instead of one; the four stats now map 1:1 onto the genre reference, so future balance questions have somewhere to look.

**Harder:** every seeded combat test had to be re-read rather than sed'd — an `agility` value meaning "never dodges" becomes `dexterity`, one meaning "acts first" becomes `speed`, and the two are not interchangeable; combat has one more knob to balance and the knobs now interact multiplicatively; `combat_logs` rows written before this ADR carry `agility` in their JSON and rows after carry `speed`/`dexterity`, so any future log reader must tolerate both shapes (nothing reads them today).

**Revisit post-MVP:** whether `MISS_CAP` 40 and the ÷4 divisor survive real play; whether speed should also feed damage or initiative more strongly; a crit stat (deliberately not added — four is already the ceiling for fixed columns); per-stat training costs if one stat proves dominant. **Durability and weight remain deferred** and are not on this list.

---

## Tunables (sign off or adjust)

| Constant | Proposed | Where | Effect |
|---|---|---|---|
| `MISS_CAP` | **40** (%) | CombatService | Ceiling on miss chance from a speed deficit. Stacks with `DODGE_CAP` → 85% worst-case whiff. Lower it if fights feel unresolvable; raise it to make speed dominant. |
| Miss divisor | **4** | CombatService (`effectiveMissChance`) | Speed points per 1% miss. At 4, the cap is reached at a 160-point deficit. Raise the divisor to make speed matter less per point. |

Unchanged and explicitly out of scope: `DODGE_CAP` (75), `MAX_ROUNDS` (10), `MIN_DAMAGE` (1), `GOLD_STEAL_PCT`, `HOSPITAL_MINUTES`, `XP_BASE` / `XP_PER_LEVEL` / `FARM_GAP`, every LevelingService and ADR-002 constant, `ENERGY_COST` (5) and `STAT_GAIN` (1).

## Action Items

1. [x] Addendum note on ADR-001 §Forces pointing here; ADR-001's own text left otherwise untouched.
2. [x] Migration: `characters.agility` → `speed` + `dexterity` (backfilled); `items.agility_delta` → `speed_delta` + `dexterity_delta` (backfilled).
3. [x] `CombatService`: `MISS_CAP`; `effectiveStats()` returns four stats; `effectiveDodgeChance(int $dexterity)`; new `effectiveMissChance(int $attackerSpeed, int $defenderSpeed)`; miss-before-dodge in `resolveTurn()` with the zero-chance short-circuit; turn order on speed.
4. [x] `TrainingService::STATS` widened; Train Livewire component + Blade grow a fourth card.
5. [x] `MarketService` / market Blade / `ItemFactory` / `ItemSeeder` carry both delta columns.
6. [x] Battle Blade renders the miss line distinctly from the dodge line.
7. [x] Tests: `effectiveMissChance` (cap, zero and negative differential, symmetric speed, sub-cap scaling), miss-before-dodge ordering, rewritten `effectiveStats` shape, dodge tests re-keyed to dexterity, turn-order tests re-keyed to speed, whitelist tests re-keyed to speed/dexterity.
8. [x] `CLAUDE.md` data model + `README.md` quick-reference updated.
9. [ ] Owner signs off on `MISS_CAP` and the miss divisor (or adjusts) after play-testing.
