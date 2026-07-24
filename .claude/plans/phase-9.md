# Cursed Battle v2 — Plan: Phase 9 (Test hardening + Documentation) — NO online deploy

Status: APPROVED-PENDING. Executor: /do, impl + verify subagent per sub-phase, commit after each. Same protocol as prior plans.

**User constraint (2026-07-24):** do NOT deploy online now. The deploy runbook is WRITTEN as documentation for later; **no deploy is executed** — no VPS access, no remote commands, no DNS, nothing outward-facing.

Builds on: ed37b86 → 98283ed (Phases 0–8, full MVP feature set shipped). 81 Pest tests green, clean tree, L12.64 + LW3.8.2.

## Context — current coverage (measured)
Service layer well-covered: Combat 12, Hospital 10, Market 7, Leveling 6, Training 5, Work 5, Regen 4, Battle-UI 4, Character 2, Dashboard 1 (+ Breeze auth 18, Profile 5). **81 total.** CLAUDE.md's Phase 9 directive: *"focus coverage on combat math and economy edge cases, skip trivial accessors."* README is still the stock Laravel file; no `docs/`.

## Phase 0 — Documentation Discovery (do at execution start, keep light)
Most facts already verified this session (Pest patterns, seeded `\Random\Randomizer`, `travel()`, service idioms). The ONE thing to verify fresh before writing the runbook: **Laravel 12 production deployment specifics** against `laravel.com/docs/12.x/deployment` — exact `php artisan optimize` / `optimize:clear` subcommand set, `config:cache`/`route:cache`/`view:cache`/`event:cache`, `migrate --force`, and the recommended nginx + php-fpm layout. Reuse the already-verified regen cron line (`* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1`) and the Phase-0 structural facts (scheduler in routes/console.php, bootstrap/providers.php, no Kernel). Do NOT assume L11 deploy steps.

---

## Phase N — Test hardening (targeted gap-fill; combat math + economy edges)

Add ONLY high-value tests for genuine gaps. Do not pad with trivial-accessor tests (CLAUDE.md). Every new test mirrors existing style (Pest, seeded `Mt19937` where RNG matters, forcing stats for determinism, `travel()` for time). Full suite must stay green.

**Combat math gaps (CombatServiceTest — currently 12, none cover these):**
1. **Turn order** — higher effective agility acts first: construct a fight where the higher-agility fighter lands the KO before the slower one swings (assert the first event's `actor` is the faster fighter); and the reverse (slower-but-stronger still wins overall). Also the tie → attacker-first branch.
2. **A dodge actually occurs** — seed an `Mt19937` and give the defender high agility (e.g. 100 → 50% dodge, or 150 → 75% cap) so the log contains at least one `dodged:true, damage:0` event; assert dodge events exist and deal 0 damage. (All current combat tests use agility 0 = never dodge — the dodge branch is only unit-tested via the pure `effectiveDodgeChance`, never exercised inside a real fight.)
3. **FARM_GAP anti-farm XP halving** — a high-level attacker (e.g. level 20) beats a low-level loser (level 1), `winner.level > loser.level + 5` → assert `xp_change` is the halved value `floor((50 + 1*10)/2)`. (Currently untested; the halving branch never fires in existing fixtures.)
4. **Gold steal when loser has 0 gold** — `floor(0 * 0.10) = 0` → assert no transfer, no error, both golds unchanged (no negative gold, no divide/edge issue).
5. **Attacker wins by 10-round tiebreak (not KO)** — both survive 10 rounds, attacker higher HP → attacker wins AND loser (defender) still hospitalized + gold moved (proves non-KO wins still apply consequences; current tiebreak test only covers defender-wins).

**Economy edge cases:**
6. **Work at exact level boundaries** — character level == occupation.min_level works; character level == occupation.max_level works; one above max_level rejected (boundary already partly covered — add the exact-equals cases).
7. **Market buy when gold == cost exactly** — succeeds, gold → 0 (boundary; current tests cover insufficient, not exact).
8. **Equip swap already owning two same-type items** — own two weapons, equip A then equip B → only B equipped, A unequipped (the swap under multi-ownership; current test equips from a fresh state).

**Notes:**
- Coverage % is NOT a goal — Pest `--coverage` needs Xdebug/PCOV which may not be installed on this WAMP PHP; do NOT add a coverage gate or chase a number. Add the specific valuable tests above; that's the deliverable.
- The post-lock re-check divergence race remains deliberately untested (documented in Phase I verification — hard to force deterministically, code is correct-by-construction). Note it, don't chase it.

**Verify:** `php artisan test` green (81 + the new ~10–12); the new combat tests genuinely exercise dodge/turn-order/farm-gap (not trivially passing — e.g. the dodge test asserts a dodge event actually appears); `migrate:fresh --seed` still clean.

---

## Phase O — Documentation (written; deploy runbook NOT executed)

1. **README.md** — replace the stock Laravel README. Sections:
   - What it is (text-based PBBG; genre ref Torn/OpenDominion; solo portfolio project).
   - Stack (L12 + LW3 + Alpine + MySQL) + the architecture principle (all game logic in `app/Services/` = the "brain"; Livewire renders).
   - Local setup: PHP 8.3, MySQL (the **InnoDB-forced** note — WAMP defaults MyISAM), `.env` (DB, `QUEUE/SESSION/CACHE=database`), `composer install`, `php artisan migrate --seed`, `npm install && npm run build`, `php artisan serve`, and **`php artisan schedule:work`** for energy/health regen in dev (without it, no regen).
   - How to play: register → character auto-created → Work (energy→gold, +xp trickle) → Train (energy→stat) → Market (buy/equip) → Battle (attack → round-by-round; win = gold+xp, lose = hospitalized) → Leveling (xp thresholds, heal on level-up).
   - Game rules quick-ref: dodge cap 75%, 10-round bounded combat, 30-min hospital, 10% gold steal, level curve L×100 (point to ADR-001 for the full spec).
   - Running tests (`php artisan test`); project status (**MVP feature-complete**, Phases 0–8; deploy pending).
   - Keep it honest and concise — no invented features, no badges for CI that doesn't exist.

2. **docs/DEPLOY.md** — Hetzner VPS runbook, clearly headed **"NOT YET EXECUTED — reference for when you deploy."** Content (verified against L12 deployment docs in Phase 0):
   - Server prereqs (PHP 8.3 + required extensions, MySQL/InnoDB, nginx, php-fpm, composer, node).
   - nginx + php-fpm serving `public/`; the server-block essentials.
   - Prod `.env` (APP_ENV=production, APP_DEBUG=false, real APP_KEY, DB creds, `QUEUE/SESSION/CACHE` drivers).
   - Release steps: `composer install --no-dev --optimize-autoloader`, `php artisan migrate --force`, `npm ci && npm run build`, `php artisan storage:link`, `php artisan optimize` (config/route/view/event cache), storage/bootstrap-cache perms.
   - **The regen scheduler CRON** (load-bearing — no regen without it): `* * * * * cd /path-to-app && php artisan schedule:run >> /dev/null 2>&1`.
   - Queue worker note: `QUEUE_CONNECTION=database` — nothing is queued today (regen is a scheduled command, not a job), so a worker is optional now; if jobs get added later, run `php artisan queue:work` under Supervisor.
   - Rollback: redeploy previous release + `migrate:rollback` caveat + `optimize:clear`.
   - A short "deploy-checklist" (env set, key generated, migrations reviewed, build succeeds, cron installed, backups) — but DO NOT run any of it.

3. Optional: a one-line pointer in README to `.claude/adr/ADR-001` and the `.claude/plans/` as the design record. Skip a separate ARCHITECTURE.md (ADR + CLAUDE.md already cover it — YAGNI).

**Verify:** README has no stale/stock Laravel boilerplate left; DEPLOY.md exists under `docs/` and is explicitly marked not-executed; both are accurate to the actual repo (commands work as written for local setup — spot-check the local-setup block against a real `migrate --seed`/`test`). Confirm NO deploy/remote/DNS command was run anywhere.

---

## Phase P — Final verification + close-out

1. `php artisan test` → full suite green (81 + new).
2. `migrate:fresh --seed` clean; app boots (`serve` + curl /login 200 — the only "run", purely local).
3. Docs accuracy: local-setup steps in README actually match the repo (someone could clone-and-run from them).
4. **No-deploy confirmation:** no outward-facing action taken — no ssh/scp/rsync/DNS/VPS command in history; DEPLOY.md is documentation only.
5. Scope: still within MVP; no new features snuck in under "tests/docs."
6. Commit per sub-phase (N tests, O docs). Update the memory index / MEMORY.md if any new durable fact emerged.
7. Report project status: MVP complete, deploy deferred by user choice; `/documentation` or a future session can run the actual deploy when ready.

## Out of scope (per user + CLAUDE.md)
Actual online deployment (VPS provisioning, remote commands, DNS, TLS) — the runbook documents it; execution is the user's call for later. Also still out: everything on CLAUDE.md's permanent do-not-build list, and any coverage-percentage gate.

## Suggested commit messages
- N: `test: cover combat turn-order, dodge, anti-farm XP, and economy boundary edges`
- O: `docs: project README + Hetzner deploy runbook (not yet executed)`
