# Cursed Battle

Text-based, browser-first persistent multiplayer RPG (PBBG). Solo passion/portfolio project — think Torn or OpenDominion, where battles resolve instantly against stored stats, not live real-time combat. Characters work jobs for gold, train for stat gains, equip gear, attack rivals, and heal over time. Losing a fight puts you in the hospital for 30 minutes; leveling up restores health.

## Stack

- **Laravel 12** — backend framework
- **Livewire 3** — reactive server-driven UI components
- **Alpine.js** — bundled with Livewire 3
- **MySQL** — persistent game state
- **Hetzner VPS** — production target (not yet deployed)

### Architecture principle (non-negotiable)

**All game logic lives in plain PHP service classes under `app/Services/` (the "brain"), fully decoupled from Livewire components.** Components call services and render results; they never contain calculation logic. This way the brain survives a future client swap (Livewire text UI → visual/mobile) without a rewrite.

## Local setup

### Requirements
- PHP 8.3 or higher
- Composer
- Node.js
- MySQL (via WAMP)

### Steps

1. **Clone and install dependencies:**
   ```bash
   composer install
   ```

2. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

3. **Important: MySQL engine note**
   This machine's WAMP defaults to MyISAM. The app forces InnoDB in `config/database.php` (required for utf8mb4 unique keys and foreign keys). Create the database:
   ```bash
   mysql -u root -e "CREATE DATABASE cursed_battle CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

4. **Run migrations and seed initial data:**
   ```bash
   php artisan migrate --seed
   ```
   Seeds 4 occupations (work jobs) and 6 items (weapons/armor).

5. **Install and build frontend:**
   ```bash
   npm install
   npm run build
   ```

6. **Start the development server:**
   ```bash
   php artisan serve
   ```
   Open http://localhost:8000 in your browser.

7. **Start the scheduler (second terminal) — REQUIRED for regen:**
   ```bash
   php artisan schedule:work
   ```
   Without this, energy and health never regenerate. The regen ticks every 5 minutes.

## How to play

1. **Register** — create a user account. A character is auto-created with default stats (level 1, 100 gold, 100 HP, 10 energy, and 5 in each of strength/defense/agility).
2. **Work** — `/work` puts you on a *shift*. Your whole energy bar is spent the moment you clock on, and the gold plus a small XP trickle land when the shift ends (5 minutes at level 1, longer as you rise). The starting job, Grave Digger, pays 2 gold per energy; higher-tier jobs pay more but gate on level. This is the core economy loop.
3. **Train** — `/train` puts you in the yard for a *drill*. 5 energy is spent up front and the +1 to strength, defense or agility lands when the drill finishes (5 minutes at level 1, longer as you rise). Permanent gains; no cap per level.
   - **While a shift or drill is running you are locked out of everything else** — no second session, no attacking, no market. Thou art at thy labours; come back when the work is done. A countdown shows in the status bar and on the Work/Train page.
   - Nothing needs collecting: the result lands on its own the next time a page paints. Walking away just means seeing it later.
   - Being *busy* does not protect you — others can still attack you mid-shift. Only the hospital blocks incoming attacks.
4. **Market** — `/market` browse and buy weapons/armor, equip them. Gear carries stat bonuses. Costs gold and has level requirements.
5. **Battle** — `/battle` attack another character. Fight resolves instantly in up to 10 rounds. Win = steal 10% of their gold + XP, lose = hospitalized for 30 minutes.
6. **Hospital** — `/hospital` view remaining cooldown. While hospitalized, you can't fight (both attacking and being attacked are blocked). Work and Train stay available, and a shift you were already on runs to completion normally.
7. **Level up** — accumulate XP via Work trickle and combat wins. Level thresholds are `level × 100` XP. Leveling up heals you fully, restores energy, and raises your max HP/energy caps.

## Game rules quick-reference

| Rule | Value |
|------|-------|
| Dodge chance | min(agility ÷ 2, **75%** hard cap) |
| Combat rounds | max 10; resolve by remaining HP if no knockout |
| Hospital cooldown | 30 minutes (blocks combat both directions only) |
| Gold steal per win | 10% of loser's gold |
| XP per level-up | Level × 100 XP threshold |
| Anti-farming | XP halved if winner's level > loser's level + 5 |
| Train session | 5 min + 30s per level above 1 (5m at L1 → 29m30s at L50) |
| Work session | 5 min + 60s per level above 1 (5m at L1 → 54m at L50) |
| While busy | blocks Train, Work, Battle (as attacker) and Market — **not** being attacked |

For the full combat spec (damage formula, turn order, effective-stat aggregation, farming anti-cheat), see [.claude/adr/ADR-001-combat-hospital-leveling.md](.claude/adr/ADR-001-combat-hospital-leveling.md). For the session pacing model (duration formula, lazy resolution, the full lock), see [.claude/adr/ADR-002-timed-sessions.md](.claude/adr/ADR-002-timed-sessions.md).

## Testing

Run the full test suite (133 Pest tests, focused on service-layer logic and economy edges):
```bash
php artisan test
```

Tests cover combat math (dodge, damage floor, KO vs tiebreak, turn order, anti-farming), economy boundaries (level gates, exact-gold purchases, item swaps), regen atomicity, and the timed-session lifecycle (duration table, nothing paid before the timer, exactly-once payout under concurrent resolves, snapshot payouts that survive the occupation being retuned or deleted mid-shift, and the busy lock on every surface). See `tests/Feature/` for specifics.

## Project status

**MVP feature-complete** (Phases 0–8):
- ✓ Auth + character creation
- ✓ Energy/health scheduler regen
- ✓ Work (gold economy)
- ✓ Train (stat gains)
- ✓ Market (buy/equip gear)
- ✓ Battle (instant-resolve PvP + combat logs)
- ✓ Hospital (30-min cooldown)
- ✓ Leveling (XP thresholds, pool scaling)
- ✓ Timed Train/Work sessions with the full busy lock (ADR-002)
- ✓ SPA navigation (`wire:navigate`) + persistent status bar

**Not yet built** (deliberately deferred per project scope):
- Quests, tournaments, clans
- Live/real-time combat (current model is instant-resolve only)
- Class system, localization, monetization
- Pay-to-heal, energy cost per attack

**Deployment** pending. A production runbook lives in `docs/DEPLOY.md` (for reference; not yet executed).

## Design record

- **CLAUDE.md** — project identity, stack lock, architecture principle, MVP scope
- `.claude/adr/ADR-001-*` — combat formula, hospital, leveling spec + tunables
- `.claude/adr/ADR-002-*` — timed Train/Work sessions: duration formula, storage shape, lazy resolution, the full lock
- `.claude/plans/` — phase-by-phase build notes (Phases 0–9)

## Tech docs

- [Laravel 12](https://laravel.com/docs/12.x)
- [Livewire 3](https://livewire.laravel.com)
- [Pest](https://pestphp.com) — test framework
