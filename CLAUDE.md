# Cursed Battle — Project Memory (V2)

Read this fully before doing anything. It encodes decisions already made — don't relitigate them, don't propose stack/framework changes.

## What this is
Text-based, browser-first persistent multiplayer RPG (PBBG). Solo passion/portfolio project, non-revenue. Genre reference: OpenDominion, Torn — attack resolves against stored stats, not live twitch combat.

## Stack (locked)
Laravel 12 + Livewire 3 + Alpine.js + MySQL, Hetzner VPS. Do not suggest switching frameworks/languages — already evaluated. Only revisit if live simultaneous combat becomes the primary mode and Reverb proves insufficient (not applicable now).

## Architecture principle (non-negotiable)
All game logic — combat resolution, economy math, training math — lives in plain PHP service classes under `app/Services/`, fully decoupled from Livewire components. Livewire components call services and render results; they never contain calculation logic. This is so the "brain" survives a future client swap (Livewire text UI → visual/mobile) without a rewrite.

## MVP scope — build only this
Auth, character, work, train, market/equip, instant-resolve battle, hospital cooldown, leveling, faction selection.

**Faction selection** (added to MVP scope 2026-08-30, built — not a deferred item): a required, permanent two-way faction pick at character creation, and a market that only sells a character universal wares plus its own faction's. That is the whole slice.

**Do not build, even if it seems natural to add:** quests, tournaments, clans, live/real-time combat, a class system, Arabic/MENA theming or localization, any monetization, faction switching, or the nation-vs-nation scheduled battle event (that is the intended phase 2 of factions, deliberately not started). These are deliberately deferred — if a task seems to need one of these, flag it instead of building it.

Faction **display names are not decided** — the DB keys are deliberately generic (`faction_1`, `faction_2`, in `Character::FACTIONS`) so naming can change without touching schema or queries, and the UI labels are placeholders. Leaning fictional rather than a real dynasty or caliphate; do not invent final names.

## Data model (decided this session — add to knowledge doc if not already there)

- `users` — standard Laravel auth fields
- `characters` (1:1 `users`, its own table — NOT merged into `users`): faction (not null, picked once at creation, immutable for MVP), level, xp, gold, health, max_health, energy, max_energy, strength, defense, agility, hospitalized_until (nullable timestamp), opponent_id (nullable self-FK — the one revealed battle mark) + opponent_rerolls (unsigned int, default 0). The last two are the opponent search: server-side so a refresh or logout can't buy a free re-roll. `OpponentService` owns them; `CombatService::resolve()` zeroes them when a fight is committed.
- `occupations` (this doc originally said `jobs`; renamed because Laravel's queue owns a `jobs` table and QUEUE/SESSION/CACHE run on the DB driver — the name collided): name, description, min_level, max_level, gold_per_energy, timestamps
- `items`: name, type (weapon/armor), faction (nullable — NULL is universal, sold to everyone), strength_delta, defense_delta, agility_delta, min_level, cost, image. Market listing filters to `faction IS NULL OR faction = character.faction`, and `MarketService::buy()` re-checks it (the item id is client input).
- `character_items`: character_id, item_id, equipped (bool)
- `combat_logs`: attacker_id, defender_id, attacker_level, defender_level, attacker_stats (json snapshot), defender_stats (json snapshot), events (json round-by-round), winner_id, gold_change, xp_change, created_at

Stats are **fixed columns** (strength/defense/agility), not a skills-pivot table. An earlier prototype used a `skills` + `user_skills` + `weapon_skills` pivot model — deliberately not carried forward for MVP; three stats don't justify the join overhead. Revisit only if a class system or a larger stat pool gets added post-MVP.

## Combat — draft formula, NOT yet finalized

> **SUPERSEDED by [ADR-001](.claude/adr/ADR-001-combat-hospital-leveling.md) (Accepted 2026-07-24).** Phases 6–8 shipped against the ADR; it is the authoritative combat/hospital/leveling spec. The draft below is kept for history only.

Carried forward from an earlier prototype and adjusted. **Run `/architecture` to formalize this into an ADR before implementing Phase 6** — treat it as a draft, not a locked spec.

```
damage = attacker.strength - defender.defense, then apply ± rand(0, attacker.level) variance
dodge_chance = min(defender.agility / 2, 75)   // HARD CAP at 75% — prototype had no cap, that was a bug
turn order = higher agility goes first (or a dedicated speed stat — decide in the ADR)
max 10 rounds, then resolve by remaining health if no KO
```

MVP is instant-resolve: this must be a single synchronous, bounded loop inside one service call — never a `while(true)` with real-time timers.

`CombatService::resolve(Character $attacker, Character $defender): CombatResult` is the only entry point. The defender is not client input — the Battle component reads it from `characters.opponent_id` (see `OpponentService`), replacing the old list-of-all-opponents view. It must check `hospitalized_until` on both characters before running anything else and reject the attack if either is still hospitalized.

## Regen
Energy/health regen ticks run via Laravel's scheduler (cron), not client-side timers, not lazy per-request timestamp math. This is locked — a lazy-timestamp alternative was considered and explicitly set aside in favor of a visible, global tick feel matching the genre reference games.

## Content ported from the old prototype
Old repo: github.com/MouhamadHamadani/Cursed-Battle (unreachable/private as of this writing — confirm access before assuming any of this is directly available in this codebase).
- Job tiers (names, descriptions, level gates) — reusable, but rework the payout mechanic from `min_hours/max_hours` (idle-timer job) to `gold_per_energy` (energy-spend job) to match the Work design above.
- `battle_histories` schema shape (stats-snapshot + events JSON + a `read` flag) — informed the `combat_logs` design above.
- If a `cursed battle weapons.xlsx` still exists from the old prototype, it's a fast path to seed `items` — ask before assuming it's available.
- Do NOT port: the live `while(true)`-with-real-time-timers battle loop, combat logic embedded directly in a Livewire component, Jetstream `current_team_id`/teams leftovers, the old `MainController` monolith.

## Build order
0. Bootstrap — Laravel 12 + Livewire 3 install, `app/Services` structure, placeholder theme
1. Auth + `characters` table
2. Energy/health regen (scheduler)
3. Work
4. Train
5. Market/Equip
6. Combat (`CombatService`) + `combat_logs` — formalize the formula via ADR first
7. Hospital cooldown
8. Leveling
9. Tests (Pest, service-layer focused) + docs + deploy prep

## Plugins to use while building this
- `/make-plan` before starting each phase group (0–2, 3–5, 6–8, 9) — include a **Documentation Discovery** pass against the actual installed Laravel 12 / Livewire 3 docs before writing code. Laravel 12 is recent enough that stale training data on syntax is a real risk — verify, don't assume.
- `/do` to execute a plan once it's made — keeps implementation work in subagents with fresh context per phase instead of one long thread.
- `/architecture` for the combat formula ADR before Phase 6, and later (post-MVP, not now) for the long-term combat model and theme decisions listed as open.
- `/testing-strategy` at Phase 9 for the `CombatService` / `WorkService` / `TrainingService` test plan — focus coverage on combat math and economy edge cases, skip trivial accessors.
- `/documentation` at the end of Phase 9 for the README and deploy runbook.
