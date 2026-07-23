# Cursed Battle v2 — Plan: Phases 0–2 (Bootstrap, Auth + Characters, Regen)

Status: APPROVED-PENDING (awaiting user go). Executor: /do, one subagent per phase, fresh context each.
Decisions locked by user 2026-07-23: **pin Laravel 12 + Livewire 3** (CLAUDE.md as written); **Breeze Blade stack** for auth (no Volt; Livewire purely for game UI).

## Environment (verified on this machine)

- PHP CLI 8.3.14 (`C:\wamp64\bin\php\php8.3.14\php.exe`, in PATH) — meets L12 requirement >= 8.2
- Composer 2.8.10, Node v24.15.0 / npm 11.14.1
- Laravel installer 4.5.1 exists but CANNOT pin versions — do not use it; use `composer create-project`
- MySQL via WAMP (root / empty password, localhost). DB `cursed_battle` must be created.
- Dev server: `php artisan serve` / `composer run dev` (CLI PHP 8.3). Do NOT serve through WAMP Apache — its module PHP may be 7.4.
- Project dir path contains a space (`Cursed Battle`) — quote all paths in shell commands.

## Phase 0 output — Documentation Discovery (DONE 2026-07-23, sources: laravel.com/docs/12.x/*, livewire.laravel.com/docs/3.x/*, laravel/installer source, Packagist)

### Critical version facts
- **Laravel 13 and Livewire 4 are the current releases.** Bare `laravel new` → L13; bare `composer require livewire/livewire` → LW4. ALWAYS pin: `"laravel/laravel:^12.0"`, `livewire/livewire:^3.0`.
- Official Livewire starter kit = L13 + LW4 + Fortify + Flux — INCOMPATIBLE with this stack. Breeze v2.4.2 (maintained, supports illuminate ^12.0) is the auth scaffold.
- Livewire 3 docs live under `livewire.laravel.com/docs/3.x/<page>` (default domain now serves v4 docs).

### Allowed APIs (verified, with source)
| Concern | Current API | Source |
|---|---|---|
| Scheduler | `routes/console.php`: `use Illuminate\Support\Facades\Schedule;` `Schedule::command('x')->everyFiveMinutes();` | 12.x/scheduling "Defining Schedules" |
| Scheduler local | `php artisan schedule:work`; list: `php artisan schedule:list` | 12.x/scheduling "Running the Scheduler" |
| Prod cron (deploy phase, not now) | `* * * * * cd /path && php artisan schedule:run >> /dev/null 2>&1` | same |
| Providers | `bootstrap/providers.php` array; `make:provider` auto-registers | 12.x/providers |
| Event listeners | auto-discovered from `app/Listeners` by type-hinted `handle()`; `php artisan make:listener X --event=Y` | 12.x/events |
| Registration event | `Illuminate\Auth\Events\Registered` (Breeze fires it in RegisteredUserController) | 12.x/verification |
| Casts | `protected function casts(): array` METHOD on model | 12.x/eloquent-mutators "Attribute Casting" |
| Livewire components | `app/Livewire` + `resources/views/livewire`; `php artisan make:livewire Name`; class `extends Livewire\Component` | livewire 3.x/components |
| Livewire assets | auto-injected on pages with a component; `@livewireStyles`/`@livewireScripts` for manual/global placement | livewire 3.x/installation |
| Alpine | BUNDLED with Livewire 3 — never install/import separately | livewire 3.x/installation |

### Anti-pattern guards (grep-checkable; these do NOT exist in L12/LW3)
- `app/Console/Kernel.php` — gone; schedule goes in `routes/console.php`
- `RouteServiceProvider`, `EventServiceProvider $listen` — gone
- `config/app.php` `'providers'` array — gone (bootstrap/providers.php)
- `protected $casts = [...]` property — use `casts()` method
- `App\Http\Livewire` namespace — LW3 uses `App\Livewire`
- `emit()`/`emitTo()`/`dispatchBrowserEvent()` — LW3 uses `dispatch()`
- `wire:model` is DEFERRED by default in LW3; `.live` for live updates; `.lazy` → `.blur`
- Separate `import Alpine from 'alpinejs'` alongside Livewire — double-Alpine breaks the page

---

## Phase A — Bootstrap (CLAUDE.md Phase 0)

**Goal:** running Laravel 12 app, MySQL wired, Breeze Blade auth installed, Livewire 3 installed, `app/Services/` exists, dark placeholder theme, git repo.

Steps (order matters):
1. Preserve non-scaffold files: move `CLAUDE.md` and `.claude/` to a temp dir outside the project (scratchpad). Dir must be EMPTY for step 2.
2. `composer create-project "laravel/laravel:^12.0" .` (run inside the empty project dir). Verify `php artisan --version` reports 12.x — abort if 13.
3. Move `CLAUDE.md` + `.claude/` back in.
4. `git init` + initial commit (user approved via this plan). Add `.claude/plans/` to the repo (it's project memory), keep default Laravel .gitignore.
5. DB: create schema via CLI-PHP PDO one-liner (avoids mysql.exe PATH dependency):
   `php -r "new PDO('mysql:host=127.0.0.1','root','') && ..."` → `CREATE DATABASE IF NOT EXISTS cursed_battle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
6. `.env`: `APP_NAME="Cursed Battle"`, switch `DB_CONNECTION=mysql` (L12 default is **sqlite** — must change), `DB_DATABASE=cursed_battle`, `DB_USERNAME=root`, `DB_PASSWORD=` empty. Also mirror in `.env.example` (no secrets — WAMP root/empty is machine-local convention).
7. `composer require laravel/breeze --dev` then `php artisan breeze:install blade --dark --pest`
   — check `breeze:install --help` first; if `--dark`/`--pest` flags differ in v2.4, adapt to actual flags. Blade stack, dark variant, Pest tests.
8. `composer require livewire/livewire:^3.0` — verify `composer show livewire/livewire` → 3.x.
9. Alpine de-dupe (Breeze Blade ships its own Alpine; Livewire bundles Alpine):
   - Remove `alpinejs` from `package.json` and its import/start lines from `resources/js/app.js`.
   - Add `@livewireStyles` (head) + `@livewireScripts` (body end) to BOTH layouts: `resources/views/layouts/app.blade.php` and `resources/views/layouts/guest.blade.php` — this loads Livewire's Alpine on every page, including pure-Blade auth pages that have no Livewire component (auto-inject alone would skip them and break Breeze dropdowns).
   - Confirm against installed `vendor/livewire/livewire` docs/source that manual directives suppress double auto-injection (LW3 documented behavior).
10. `mkdir app/Services` + `.gitkeep`.
11. `php artisan migrate` (Breeze/user tables), `npm install`, `npm run build`.
12. Placeholder theme = Breeze dark variant + APP_NAME only. NO custom theming beyond this — deferred deliberately.

**Verification checklist:**
- `php artisan --version` → `Laravel Framework 12.*`
- `composer show livewire/livewire` → 3.x
- `php artisan test` → all Breeze auth tests green (Pest)
- `php artisan serve` + GET / → 200; /login and /register render
- `grep -r "alpinejs" package.json resources/js/` → no hits
- `app/Services/` exists; git log shows initial commit

**Anti-pattern guards:** do not run bare `laravel new` (yields L13); do not touch config/app.php providers; do not install Volt/Flux/Jetstream.

---

## Phase B — Auth + `characters` table (CLAUDE.md Phase 1)

**Goal:** every registered user gets a 1:1 character row; dashboard shows character stats via a Livewire component (proves LW3 wiring).

Auth itself already exists from Phase A (Breeze). This phase adds the character layer.

Steps:
1. `php artisan make:model Character -m`. Migration columns (CLAUDE.md data model, fixed columns NOT skills pivot):
   - `id`, `foreignId('user_id')->unique()->constrained()->cascadeOnDelete()` (unique ⇒ 1:1)
   - `unsignedInteger('level')->default(1)`, `unsignedBigInteger('xp')->default(0)`, `unsignedBigInteger('gold')->default(100)`
   - `unsignedInteger('health')->default(100)`, `unsignedInteger('max_health')->default(100)`
   - `unsignedInteger('energy')->default(10)`, `unsignedInteger('max_energy')->default(10)`
   - `unsignedInteger('strength')->default(5)`, `unsignedInteger('defense')->default(5)`, `unsignedInteger('agility')->default(5)`
   - `timestamp('hospitalized_until')->nullable()`, `timestamps()`
   - Defaults are game-balance knobs — live in the migration on purpose, tune post-MVP.
2. `Character` model: `protected $guarded = [];`, `casts()` METHOD (not property) → `'hospitalized_until' => 'datetime'`. `belongsTo(User)`. Add `hasOne(Character)` to `User`.
3. `php artisan make:listener CreateCharacterForUser --event='Illuminate\Auth\Events\Registered'` — auto-discovered from `app/Listeners`, no manual registration. `handle()`: create character for `$event->user` if none exists (idempotent). FIRST verify Breeze's `RegisteredUserController` fires `event(new Registered($user))` — grep it; it does in stock Breeze.
4. `php artisan make:livewire Dashboard` → `app/Livewire/Dashboard.php` + `resources/views/livewire/dashboard.blade.php`. Read-only render of the logged-in user's character stats (level/xp/gold/health/energy/str/def/agi). Embed `<livewire:dashboard />` inside Breeze's existing dashboard Blade view (keep Breeze's route/layout — do NOT convert to a full-page component).
5. `php artisan migrate`.

**Verification checklist:**
- Pest feature test: POST /register → assert `characters` row exists for new user with defaults (level 1, gold 100, energy 10/10, health 100/100)
- Dashboard as logged-in user shows stats rendered by the Livewire component
- DB: `user_id` has UNIQUE index; deleting a user cascades to the character
- `php artisan test` green
- `grep -r "protected \$casts" app/` → no hits (casts() method only)

**Anti-pattern guards:** no logic in the Livewire component beyond fetching + rendering (architecture rule: logic belongs in services — none needed yet for read-only display); no `App\Http\Livewire`; no skills pivot tables.

---

## Phase C — Energy/health regen via scheduler (CLAUDE.md Phase 2)

**Goal:** scheduler-driven global regen tick (LOCKED decision — cron scheduler, NOT lazy per-request timestamp math, NOT client-side timers).

Steps:
1. `app/Services/RegenService.php` — first real service. Plain PHP class:
   - Tunable constants: `ENERGY_PER_TICK = 1`, `HEALTH_PER_TICK = 10` (initial balance: empty→full energy in ~50 min, matches genre pacing; tune freely later).
   - `tick(): void` — two bulk UPDATEs, no per-row loops, capped at max via SQL `LEAST()`:
     `Character::whereColumn('energy', '<', 'max_energy')->update(['energy' => DB::raw('LEAST(energy + 1, max_energy)')]);` (same shape for health). Interpolate the constant into the raw string via sprintf with `(int)` cast — it's a class constant, not user input, but keep it explicitly integer.
   - Hospitalized characters DO regen (they just can't act — enforced later by CombatService in Phase 6/7). One-line comment noting this MVP simplification.
2. `php artisan make:command RegenTick` — signature `game:regen-tick`, description, `handle()` calls `(new RegenService)->tick()` (or resolve from container). Thin wrapper, zero logic.
3. Schedule in **`routes/console.php`** (NOT a Kernel — doesn't exist):
   ```php
   use Illuminate\Support\Facades\Schedule;
   Schedule::command('game:regen-tick')->everyFiveMinutes();
   ```
   Copy shape from 12.x/scheduling "Defining Schedules".
4. Pest unit test for RegenService (the one runnable check for non-trivial logic; full test pass is Phase 9): character below max regens by constant; character at max stays at max (LEAST cap works); both energy and health.
5. Document local runner in README-stub or plan notes: `php artisan schedule:work` during dev. Production cron line goes in deploy runbook (Phase 9) — not now.

**Verification checklist:**
- `php artisan schedule:list` → shows `game:regen-tick` every 5 minutes
- Manually set a character to energy 3, health 50 → `php artisan game:regen-tick` → energy 4, health 60; run repeatedly → caps exactly at max_energy/max_health, never exceeds
- Pest test green; `php artisan test` green
- `ls app/Console/` → no Kernel.php anywhere in the repo

**Anti-pattern guards:** no `app/Console/Kernel.php`; no regen math inside the command or any Livewire component (service only); no per-character loop (bulk UPDATE only); no client-side timers.

---

## Phase D — Final verification (make-plan mandated)

1. Grep sweep for every anti-pattern in the Phase 0 table:
   - `Glob app/Console/Kernel.php` → nothing
   - `grep -r "App\\\\Http\\\\Livewire" app/` → nothing
   - `grep -r "protected \$casts" app/` → nothing
   - `grep "RouteServiceProvider\|EventServiceProvider" app/ bootstrap/ config/` → nothing
   - `grep "alpinejs" package.json` → nothing
2. Version pins: `composer show laravel/framework` → 12.x; `composer show livewire/livewire` → 3.x; composer.json has `^12.0` / `^3.0` constraints.
3. End-to-end flow: register fresh user → characters row auto-created with defaults → drain energy manually → run `game:regen-tick` → regen applied and capped → dashboard reflects it.
4. `php artisan test` → entire suite green.
5. Commit per phase completed (A, B, C each its own commit).

## Out of scope (do NOT build — flag if a step seems to need them)
Quests, tournaments, clans, live combat, class system, theming/localization, monetization, combat formula (Phase 6 — requires /architecture ADR first), work/train/market (Phases 3–5).
