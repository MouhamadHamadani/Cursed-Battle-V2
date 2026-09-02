# ADR-003: Four Battle Stats & Four Equipment Slots

**Status:** Accepted (2026-09-01) — decided with the project owner across several sessions and **not pending further sign-off**. The numeric values in §Tunables are adjustable defaults, flagged the same way ADR-001/002 flag theirs.
**Date:** 2026-09-01
**Deciders:** Mouhamad (project owner) — the stat split, the slot model and the `type` → `slot` replacement are all decided. This ADR records the reasoning and proposes the numbers.
**Supersedes:** one line of [ADR-001](ADR-001-combat-hospital-leveling.md) §Forces — "three fixed stat columns — no new 'speed' stat". Nothing else in ADR-001 moves: hospital, leveling, gold-steal and the XP formulas are untouched. ADR-001 carries a dated addendum note pointing here.
**Related:** [ADR-002](ADR-002-timed-sessions.md) — the trainable-stat whitelist and `activity_stat` column widen from three names to four; the session model itself is untouched.

---

## Context

Two changes land together because they share the same consumer (`CombatService::effectiveStats()`) and the same migration window. Splitting them into two ADRs would mean two passes over the same seven files and two rebalances of the same numbers.

### Where combat stands today

Characters carry three fixed stat columns — `strength`, `defense`, `agility` — and `CombatService::resolveTurn()` uses them like this:

1. **dodge** — `effectiveDodgeChance(agility)` = `min(agility ÷ 2, 75)`. Under the roll, the swing deals nothing.
2. **damage** — `max(MIN_DAMAGE, strength − defense ± rand(level))`.
3. **turn order** — higher effective agility acts first; exact tie → attacker.

**Every swing that is not dodged lands.** There is no accuracy check anywhere in the model. A fighter's only way to avoid a blow is the defender-side dodge roll, and an attacker has no stat that makes their own swings connect more reliably. Offence has exactly one lever (raw strength); defence has two (defense, agility). That is a real gap, not a cosmetic one.

Items carry `type`, one of `weapon` | `armor`, and `MarketService::equip()` enforces one equipped item per `type` — so a character can wear exactly one weapon and exactly one armor piece. Two slots is the whole equipment model.

### Genre research

| Game | Battle stats | Equipment |
|---|---|---|
| **Torn** (this project's stated genre reference, ADR-001 §Forces) | **4** — Strength, Defense, **Speed**, **Dexterity**; Speed governs hit chance, Dexterity governs dodge | **5 armor slots**; overlapping coverage takes the **highest** value rather than summing; its "Body Armor" slot already lumps chest/arms/hands/groin/legs together |
| Kingdom of Loathing | 3 | — |
| Mafia Wars | 2 — Attack/Defense only | — |

Torn's 4-stat split maps exactly onto the gap above: Speed is the accuracy stat this model lacks, Dexterity is the dodge stat it already has under another name. Neither KoL nor Mafia Wars models accuracy at all, and neither is the reference this project chose.

### Historical armor terminology

Medieval harness is conventionally described as **head protection, torso protection, and limb protection**, with the **shield as a distinct carried implement** — borne, not worn. That distinction is the reason `shield` is its own slot here rather than another armor piece: it is the one defensive item you *hold*, which is what makes a defense-up / mobility-down trade-off read as natural rather than arbitrary.

### Forces
- **ADR-001 §Forces said "no new speed stat."** This ADR reverses precisely that clause, on the owner's decision, and nothing adjacent to it. The line stays in ADR-001's historical text with a dated addendum pointing here — ADR-001 is a record, not a living document.
- **CLAUDE.md's stats rationale still holds.** "Fixed stat columns, not a skills-pivot table … three stats don't justify the join overhead." Four don't either. This is a two-column-wide schema change, not a re-architecture, and it does **not** reopen the skills-pivot decision.
- **One aggregation point.** ADR-001 made `CombatService::effectiveStats()` the single place base stats and equipped-gear deltas are summed. Four stats and four slots both widen *that one function*; no second aggregation appears.
- **Untrusted client input.** `TrainingService`'s stat whitelist is a column-injection guard (`$stat` is interpolated into SQL). It widens to four names; it does not loosen.
- **`combat_logs` is immutable and append-only.** Rows already written carry `agility` in their JSON snapshots and stay exactly as they are — records of fights fought under the three-stat model. New rows carry `speed`/`dexterity`. Nothing reads these snapshots programmatically, so no reader needs a compatibility branch.
- **Determinism in tests.** `CombatService` takes a constructor-injected `\Random\Randomizer`; the suite seeds `Mt19937` and asserts exact round-by-round outcomes. A new RNG draw per swing would shift every seeded sequence in the file. This constrains the implementation — see the short-circuit in §2.
- **Going from 1 to 3 defense-contributing slots inflates the gear ceiling.** This is the one force that makes the two halves of this ADR genuinely coupled: slots change what the stat numbers *mean*, so the delta ranges have to move in the same pass (§4).

---

## Decision

### 1. Four battle stats

`characters`: `agility` → **`speed`** + **`dexterity`**, both `unsignedInteger` default **5** (the same default `agility` carried, so a new character starts 5/5/5/5).

`items`: `agility_delta` → **`speed_delta`** + **`dexterity_delta`**, both signed `integer` default **0**.

`dexterity` inherits agility's existing role (dodge) unchanged. `speed` is new and governs hit chance. `combat_logs` needs no migration — its stat snapshots are JSON.

### 2. The combat mechanic — **adjustable defaults**

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

`max(0, …)` means a faster-or-equal attacker **never misses** — the floor is 0%, so speed parity costs nothing and speed is purely a contest.

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
    → event{missed: true, dodged: false, damage: 0}      # distinct log line; dodge is NOT rolled
dodgeChance = effectiveDodgeChance(target.dexterity)
if roll(1,100) <= dodgeChance:
    → event{missed: false, dodged: true, damage: 0}      # today's line, verbatim
→ damage as today
```

The two are **semantically distinct and rendered distinctly**: a miss is the attacker's failure ("swings wide, and the blow finds only air"), a dodge is the defender's success ("slips the blow"). Every round event carries both `missed` and `dodged` booleans so the JSON shape stays uniform.

**Worst case is bounded and deliberate:** 40% miss × 75% dodge = **85%** of swings can whiff against a maximally invested defender, up from 75% today. `MIN_DAMAGE = 1` and the `MAX_ROUNDS = 10` cap still guarantee termination and a decided outcome, so this cannot stall a fight — it only makes a speed/dexterity build harder to hurt. If 85% reads as too high in play, `MISS_CAP` is the one knob to turn.

**The `missChance > 0` short-circuit is load-bearing, not an optimisation.** A 0% chance can never fire, so skipping the draw is semantically identical — and it keeps the RNG stream byte-identical for every equal-speed fight, which is every pre-existing seeded test. Without it, adding this feature would silently re-roll every assertion in `CombatServiceTest`. The dodge roll is deliberately **not** given the same guard: changing when *it* draws would shift the streams the existing tests were written against.

### 3. Turn order moves to speed

Turn order was "higher effective **agility** acts first, exact tie → attacker." Agility no longer exists, so this has to be re-homed, and **speed** is the only honest choice: acting first is a question of who is quicker, not of who is harder to hit. Dexterity stays purely defensive — dodge and nothing else. Tie-break unchanged (attacker acts first).

Speed therefore does two jobs (accuracy + initiative) and dexterity one (evasion). That asymmetry is intentional: it gives speed a reason to exist for a pure-offence build, which a hit-chance-only stat would not.

### 4. Four equipment slots

`items.type` (`weapon` | `armor`) is **replaced** by `items.slot`, one of:

| Slot | What it is | Notes |
|---|---|---|
| `weapon` | the held offensive implement | full delta range retained |
| `shield` | the **carried** defensive implement | new; the defense-up / mobility-down trade-off lives here |
| `head` | head protection | new |
| `body` | a **full worn suit** — chest, arms, legs and feet abstracted into one piece | replaces the old `armor` |

`slot` **fully replaces** `type`; nothing references `type` afterward.

**Why `body` is one piece.** Torn's own "Body Armor" slot already lumps chest/arms/hands/groin/legs together, and Torn still separates pants/gloves/boots on top of that. Merging those into `body` is **one step simpler than Torn**, which is the intended simplification — four slots is a model a solo project can actually populate with content, five-plus is a content treadmill.

**Why `shield` is separate rather than a second armor piece.** Historically the shield is borne, not worn (§Context). Mechanically that is the hook: it is the one defensive item where "bulkier, harder to swing fast or dodge behind" is the obvious flavour, so it is the natural home for **positive `defense_delta` paired with a small negative `speed_delta` and/or `dexterity_delta`**. No other slot carries a built-in penalty.

`MarketService::equip()` already unequips same-`type` items before equipping; re-keying that one `where()` to `slot` generalises it from 2 slots to 4 **with no other logic change**. That is the whole implementation cost on the service side.

**Not adopted from Torn: highest-value-wins coverage.** Torn resolves overlapping armor coverage by taking the highest value rather than summing. That only makes sense when slots physically overlap on a body map, which is exactly the complexity the `body` merge removes. Deltas here **sum**, as they already do — one fewer rule, and `effectiveStats()` stays a plain sum.

### 5. Item delta rebalance — **adjustable defaults**

Going from 1 armor slot to 3 defense-contributing slots (`shield`, `head`, `body`) roughly **triples the gear-derived defense ceiling**, even with the chest/arms/legs/feet merge. Left alone, the split would be a silent across-the-board buff to defence that nobody asked for.

So the **non-weapon** delta ranges scale down to roughly a third; weapons keep the full range:

| | Today | After |
|---|---|---|
| `weapon` deltas | `numberBetween(0, 10)` | **`0..10`** (unchanged) |
| `shield` / `head` / `body` deltas | `numberBetween(0, 10)` | **`0..3`** |
| `shield` `speed_delta` / `dexterity_delta` | — | **`-3..-1`** (both) |

Ceiling check: 3 slots × max 3 = **9**, against today's single slot × max 10 = **10**. Near-identical, which is the point — the *shape* of gear changes, the total power does not.

The shield's penalty applies to **both** `speed_delta` and `dexterity_delta`, not one or the other: a shield is both harder to swing quickly around and harder to dodge behind. It gets the same `defense_delta` range as a body piece (`0..3`), so its cost is the mobility hit, not a smaller defensive payoff.

These live as named constants on `ItemFactory` so they are retunable in one place.

### 6. Item description

`items` gains a nullable `description` (`text`) — pure flavour text, no mechanical effect, rendered wherever item details show.

### 7. Migration plan — **backfill, not drop-and-recreate**

The dev DB was inspected before choosing: **6 characters, all sitting at the default `agility` 5**, and **8 items whose deltas match `ItemSeeder` exactly** (nothing hand-tuned). By the letter of the brief that is disposable and a clean recreate would have been allowed.

**Backfill was chosen anyway** — it is a handful of extra statements, removes the "was anything hand-edited since?" question entirely, and it is the migration that would be *correct* on a populated database, which is the one that matters if this ever runs anywhere but this laptop.

```
characters:  add speed, dexterity  →  UPDATE SET speed = agility, dexterity = agility  →  drop agility
items:       add speed_delta, dexterity_delta
             →  UPDATE SET speed_delta = agility_delta, dexterity_delta = agility_delta
             →  drop agility_delta
items:       add slot (nullable)
             →  UPDATE SET slot = (type = 'weapon' ? 'weapon' : 'body')
             →  make slot NOT NULL  →  drop type
```

Two calls inside that:

- **Both new stat columns take the same old value** rather than splitting it — a character with 5 agility becomes 5 speed *and* 5 dexterity. Halving would silently nerf every existing character; duplicating is a free, obviously-correct grant at this population size.
- **Every existing `armor` row maps to `body`**, the simplest defensible default. `slot` is added **nullable** and only made `NOT NULL` after the backfill, deliberately: a `DEFAULT 'body'` left on a mechanical field is a footgun, since an `Item::create()` that forgets `slot` would silently produce body armor. There is no sensible default for this column, so it gets none.

The one consequence worth stating plainly: the seeded **Bone Shield lands in `body`**, not `shield`, because the migration maps by `type` and not by name — a name-based special case in a migration is exactly the hardcoded-category-name pattern this project already rejected once. `ItemSeeder` moves from `firstOrCreate` to `updateOrCreate` (still idempotent, keyed on name) so re-seeding corrects it and keeps a migrated DB and a fresh one in agreement.

### 8. Explicitly out of scope — noted, not built

- **Two-handed weapons that preclude a shield.** This is the natural next step and the shield slot is what makes it interesting, but it needs a `two_handed` boolean on weapons *and* extra equip validation (equipping a two-hander must unequip the shield, and equipping a shield must be refused while a two-hander is on). Left for a future pass.
- **Durability, weight, and any repair/decay mechanic: still deferred.** (V1's durability was buggy anyway — inverted wear calculation, gated on a hardcoded category-name check — and is not worth porting.)
- **Weapon flavour subcategories (sword/axe/bow).** `slot` is a *mechanical* field, not a decorative one. A cosmetic category column is a fully independent future addition if ever wanted, and must not be conflated with `slot`.

---

## Options Considered

### How many stats

| Option | Precedent | Verdict |
|---|---|---|
| **4 — STR / DEF / SPD / DEX** | **Torn** | **Chosen** — closes the accuracy gap using the reference's own split |
| 3 — keep agility as-is | Kingdom of Loathing | Rejected — leaves offence with one lever and defence with two; no accuracy model at all |
| 2 — Attack / Defense | Mafia Wars | Rejected — strictly less depth than what already ships |
| 5+ — add luck/endurance | — | Rejected — nothing in the MVP asks for it; four already stretches the "fixed columns" justification |

### How many slots

| Option | Verdict |
|---|---|
| **4 — weapon / shield / head / body** | **Chosen** — one step simpler than Torn; `body` merges the worn suit, `shield` stays distinct because it is carried |
| 2 — keep weapon / armor | Rejected — the decision is explicitly to add depth here, and a shield has nowhere to live |
| 5+ — split out pants / gloves / boots (Torn's actual shape) | Rejected — a content treadmill for a solo project, and the marginal decision per extra slot is near zero |
| Fold `shield` into `head`/`body` as just another armor piece | Rejected — loses the carried-vs-worn distinction that makes the mobility trade-off read naturally |

### Miss formula

| Option | Verdict |
|---|---|
| **Opposed differential — `min((defSpd − atkSpd) ÷ 4, cap)`, floored at 0** | **Chosen** — parity is free, speed only matters relative to who you fight, mirrors the shape of `strength − defense` |
| Absolute — `min(defenderSpeed ÷ 2, cap)`, dodge's exact shape | Rejected — makes speed a second dodge stat and gives the attacker's own speed no effect, which is the gap this ADR exists to close |
| Ratio — `atkSpd ÷ (atkSpd + defSpd)` | Rejected — smoother, but not retunable by eye, and low-level fights (5 vs 5 → 50%) would behave nothing like high-level ones |

### Where the miss roll sits

| Option | Verdict |
|---|---|
| **Miss first, dodge only if the swing connects** | **Chosen** — reads correctly ("you swung badly" precedes "they got out of the way"), and a missed swing costs the defender no dodge roll |
| Dodge first, then miss | Rejected — a defender "dodging" a swing that was never going to land is incoherent in the log |
| One combined roll | Rejected — collapses two stats into one number and makes the distinct log line impossible |

### Overlapping-coverage resolution

| Option | Verdict |
|---|---|
| **Sum the deltas across slots** | **Chosen** — already how `effectiveStats()` works; no new rule |
| Highest-value-wins (Torn's model) | Rejected — only meaningful with a physical body map and overlapping coverage, which the `body` merge deliberately removes |

### Migration shape

| Option | Verdict |
|---|---|
| **Add → backfill → drop** | **Chosen** — correct on any database, not just this one |
| Drop the old columns, add fresh ones, re-seed | Allowed by the brief (dev data is disposable) but rejected — same file size, worse on any populated DB |
| Keep `agility` / `type` as deprecated columns alongside | Rejected outright — two sources of truth for one concept is exactly the bug this split is meant to avoid |

---

## Trade-off Analysis

- **Duplicating agility into both stat columns vs. splitting it:** duplicate. Splitting (e.g. `speed = ceil(agility/2)`) would silently take stats away from every existing character to satisfy a tidiness nobody asked for.
- **Speed owning turn order as well as accuracy:** yes, deliberately. Had dexterity kept turn order, speed would be single-purpose and dexterity triple-purpose (dodge + initiative) — the asymmetry inverted in exactly the wrong direction.
- **`MISS_CAP` 40 vs. matching `DODGE_CAP` 75:** 40. The caps stack multiplicatively; 75/75 would mean 94% of swings whiff against a specialist, with fights decided almost entirely by the round cap and the HP tiebreak. 40 keeps the stacked worst case at 85% — a meaningful step up from today's 75% without turning combat into a coin flip. This is the number most likely to want tuning after real play.
- **Scaling armor deltas to a third vs. leaving them and accepting a defence buff:** scale. A 3× gear-defence ceiling would swamp `strength − defense` and push far more fights into the 10-round HP tiebreak, which is the least interesting outcome the sim produces.
- **Shield penalty on both speed and dexterity vs. just one:** both. One would make the shield strictly better for whichever build ignored that stat; both keep it an honest trade for every build.
- **`slot` NOT NULL with no default vs. defaulting to `body`:** no default. A forgotten `slot` should fail loudly at insert, not silently become body armor.
- **Widening the trainable whitelist vs. adding a second Train page:** widen. The whitelist is a security guard, not a UI concern, and the Train page is a `@foreach` over a stat array — a fourth entry is a data change, not a code change.
- **Not editing ADR-001's superseded line in place:** an ADR is a dated record of what was decided and why. Rewriting the old line to say the opposite would destroy the audit trail that makes ADRs worth keeping. A dated addendum preserves both.

## Consequences

**Easier:** offence has two levers instead of one, so gear and training choices actually branch; a "fast" build is expressible for the first time; the miss/dodge split gives the battle log two genuinely different lines to tell; four slots make gear a loadout decision rather than a two-line checklist, and the shield gives the item catalogue its first real trade-off; `equip()` generalised from 2 slots to 4 for the cost of one changed `where()` key.

**Harder:** every seeded combat test had to be re-read rather than sed'd — an `agility` value meaning "never dodges" becomes `dexterity`, one meaning "acts first" becomes `speed`, and the two are not interchangeable; combat has one more knob to balance and the knobs interact multiplicatively; content obligation triples (three defensive slots to populate instead of one); `combat_logs` rows written before this ADR carry `agility` in their JSON while later rows carry `speed`/`dexterity`, so any future log reader must tolerate both shapes (nothing reads them today).

**Revisit post-MVP:** two-handed weapons and shield exclusion (§8 — the natural next step); whether `MISS_CAP` 40 and the ÷4 divisor survive real play; whether the `0..3` armor range needs per-slot differentiation (a helm arguably shouldn't match a full suit); a cosmetic weapon-category column; per-stat training costs if one stat proves dominant. **Durability and weight remain deferred** and are not on this list.

---

## Tunables (adjustable defaults — adjust freely, no re-decision needed)

| Constant | Default | Where | Effect |
|---|---|---|---|
| `MISS_CAP` | **40** (%) | `CombatService` | Ceiling on miss chance from a speed deficit. Stacks with `DODGE_CAP` → 85% worst-case whiff. Lower if fights feel unresolvable; raise to make speed dominant. |
| `MISS_DIVISOR` | **4** | `CombatService` | Speed points of deficit per 1% miss. At 4 the cap is reached at a 160-point deficit. Raise to make speed matter less per point. |
| `DODGE_CAP` | **75** (%) | `CombatService` | **Unchanged from ADR-001**, listed for completeness — dodge is now fed by `dexterity` instead of `agility`, but the formula and cap are identical. |
| `WEAPON_DELTA_MAX` | **10** | `ItemFactory` | Upper bound on a generated weapon's stat deltas. Unchanged from today's range. |
| `ARMOR_DELTA_MAX` | **3** | `ItemFactory` | Upper bound for `shield` / `head` / `body`. ≈ a third of the weapon range, so 3 defensive slots ≈ today's 1-slot ceiling (9 vs 10). |
| `SHIELD_PENALTY_MIN` / `SHIELD_PENALTY_MAX` | **−3 / −1** | `ItemFactory` | The shield's mobility cost, applied to **both** `speed_delta` and `dexterity_delta`. Its `defense_delta` uses `ARMOR_DELTA_MAX` like any other defensive piece. |

Unchanged and explicitly out of scope: `MAX_ROUNDS` (10), `MIN_DAMAGE` (1), `GOLD_STEAL_PCT`, `HOSPITAL_MINUTES`, `XP_BASE` / `XP_PER_LEVEL` / `FARM_GAP`, every `LevelingService` and ADR-002 constant, `ENERGY_COST` (5) and `STAT_GAIN` (1).

## Action Items

1. [x] Addendum note on ADR-001 §Forces pointing here; ADR-001's own text left otherwise untouched.
2. [x] Migration: `characters.agility` → `speed` + `dexterity` (backfilled); `items.agility_delta` → `speed_delta` + `dexterity_delta` (backfilled); `items.description` added.
3. [x] Migration: `items.type` → `items.slot` (`weapon`/`shield`/`head`/`body`), armor mapped to `body`, `NOT NULL` with no default.
4. [x] `CombatService`: `MISS_CAP` / `MISS_DIVISOR`; `effectiveStats()` returns four stats; `effectiveDodgeChance(int $dexterity)`; new `effectiveMissChance()`; miss-before-dodge in `resolveTurn()` with the zero-chance short-circuit; turn order on speed.
5. [x] `MarketService::equip()` re-keyed from `type` to `slot` — generalises to 4 slots with no other change.
6. [x] `TrainingService::STATS` widened; Train Livewire component + Blade grow a fourth card.
7. [x] `Item::SLOTS`; `ItemFactory` slot-aware with the rebalanced ranges and the shield trade-off; `ItemSeeder` populates all four slots via `updateOrCreate`.
8. [x] Market: item-details popup (`x-dark-modal`) with image, name, slot, level, cost, all four deltas, description, and the correct Buy/Equip/Unequip action — added alongside the existing on-card Buy fast path, not replacing it.
9. [x] Battle Blade renders the miss line distinctly from the dodge line.
10. [x] Tests: `effectiveMissChance` (cap, zero/negative differential, symmetric speed, sub-cap scaling), miss-before-dodge ordering, `effectiveStats` shape, dodge tests re-keyed to dexterity, turn-order tests re-keyed to speed, whitelist re-keyed, all four slots equip independently, popup render.
11. [x] `CLAUDE.md` data model + `README.md` quick-reference updated.
