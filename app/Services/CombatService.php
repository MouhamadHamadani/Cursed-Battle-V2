<?php

namespace App\Services;

use App\Models\Character;
use App\Models\CombatLog;
use Illuminate\Support\Facades\DB;

class CombatService
{
    /** Bounded round cap — guarantees resolve() terminates (never while(true)). */
    public const MAX_ROUNDS = 10;

    /** Hard cap on dodge chance (%) — the draft formula's uncapped bug, fixed per ADR-001. */
    public const DODGE_CAP = 75;

    /**
     * Hard cap on miss chance (%) from a speed deficit (ADR-003). Stacks
     * multiplicatively with DODGE_CAP: 40% × 75% = 85% worst-case whiff.
     */
    public const MISS_CAP = 40;

    /** Speed points of deficit per 1% miss chance (ADR-003 §Tunables). */
    public const MISS_DIVISOR = 4;

    /** Every landed hit deals at least this much, so HP always moves. */
    public const MIN_DAMAGE = 1;

    /** Fraction of the loser's gold stolen by the winner. */
    public const GOLD_STEAL_PCT = 0.10;

    /** Minutes the loser is hospitalized (blocked from combat only). */
    public const HOSPITAL_MINUTES = 30;

    /** Winner's XP = XP_BASE + loser.level * XP_PER_LEVEL (halved past FARM_GAP). */
    public const XP_BASE = 50;

    public const XP_PER_LEVEL = 10;

    /** Anti-farming: XP is halved if winner.level > loser.level + FARM_GAP. */
    public const FARM_GAP = 5;

    /**
     * RNG is constructor-injected so tests are deterministic: prod gets the
     * default secure CSPRNG engine, tests pass a seeded Mt19937 engine.
     * php.net/manual/en/class.random-randomizer.php,
     * php.net/manual/en/class.random-engine-mt19937.php
     */
    public function __construct(private \Random\Randomizer $rng = new \Random\Randomizer()) {}

    /**
     * Effective stats = base + Σ(equipped items' *_delta). The ONE
     * aggregation point (ADR-001) — combat and any future display share
     * this; nothing else recomputes gear deltas.
     *
     * @return array{strength: int, defense: int, speed: int, dexterity: int}
     */
    public function effectiveStats(Character $character): array
    {
        $character->loadMissing('characterItems.item');

        $equipped = $character->characterItems->where('equipped', true);

        return [
            'strength' => $character->strength + $equipped->sum(fn ($ci) => $ci->item->strength_delta),
            'defense' => $character->defense + $equipped->sum(fn ($ci) => $ci->item->defense_delta),
            'speed' => $character->speed + $equipped->sum(fn ($ci) => $ci->item->speed_delta),
            'dexterity' => $character->dexterity + $equipped->sum(fn ($ci) => $ci->item->dexterity_delta),
        ];
    }

    /**
     * Pure dodge-chance calculation — single source of truth, used inside
     * the sim and directly unit-testable. Fed by dexterity since ADR-003;
     * the formula and the cap are unchanged from ADR-001.
     */
    public function effectiveDodgeChance(int $dexterity): int
    {
        return min(intdiv($dexterity, 2), self::DODGE_CAP);
    }

    /**
     * Pure miss-chance calculation (ADR-003) — an OPPOSED roll on the speed
     * differential, not on absolute speed: you are hard to hit because you
     * are faster *than your attacker*. max(0, …) means a faster-or-equal
     * attacker never misses, so speed parity is free.
     */
    public function effectiveMissChance(int $attackerSpeed, int $defenderSpeed): int
    {
        return min(intdiv(max(0, $defenderSpeed - $attackerSpeed), self::MISS_DIVISOR), self::MISS_CAP);
    }

    /**
     * Resolve a fight. A single bounded, synchronous simulation — never a
     * real-time loop. All consequences (health, hospitalization, gold, xp,
     * the combat_logs row) are persisted atomically; the caller only renders
     * the returned CombatResult.
     */
    public function resolve(Character $attacker, Character $defender): CombatResult
    {
        // Resolve any due session before the pre-check, and outside the
        // transaction below (ADR-002 §2) — a finished shift must not block
        // an attack.
        app(ActivityService::class)->resolvePending($attacker);

        $this->assertCanFight($attacker, $defender);

        return DB::transaction(function () use ($attacker, $defender) {
            [$attacker, $defender] = $this->lockBoth($attacker, $defender);

            // Re-check under lock: hospitalization/health may have changed
            // between the pre-check above and acquiring the lock.
            $this->assertCanFight($attacker, $defender);

            $sim = $this->simulate($attacker, $defender);

            return $this->persistOutcome($attacker, $defender, $sim);
        });
    }

    /**
     * @throws GameActionException
     */
    private function assertCanFight(Character $attacker, Character $defender): void
    {
        if ($attacker->id === $defender->id) {
            throw new GameActionException('You cannot attack yourself.');
        }

        if ($attacker->isHospitalized()) {
            throw new GameActionException('You are hospitalized and cannot fight.');
        }

        // ADR-002 fork 1: being busy blocks attacking, but NOT being attacked —
        // otherwise a long shift would be a stretch of invulnerability.
        if ($attacker->isBusy()) {
            throw new GameActionException('Thou canst not take up arms while '.ActivityService::describe($attacker->activity_type).'.');
        }

        if ($defender->isHospitalized()) {
            throw new GameActionException('That target is hospitalized and cannot be attacked.');
        }

        if ($attacker->health <= 0) {
            throw new GameActionException('You have no health to fight with.');
        }
    }

    /**
     * Lock both character rows in ascending id order (lock min(id) then
     * max(id)) before reading fresh state. InnoDB deadlock avoidance when a
     * fight A→B and a concurrent fight B→A try to lock the same two rows in
     * opposite orders — our convention, not a framework requirement.
     *
     * @return array{0: Character, 1: Character} fresh, locked [attacker, defender]
     */
    private function lockBoth(Character $attacker, Character $defender): array
    {
        $ids = [$attacker->id, $defender->id];
        sort($ids);

        $locked = [];
        foreach ($ids as $id) {
            $locked[$id] = Character::query()->lockForUpdate()->findOrFail($id);
        }

        return [$locked[$attacker->id], $locked[$defender->id]];
    }

    /**
     * Pure simulation over effective stats — no persistence. Bounded by a
     * `for` loop capped at MAX_ROUNDS (≤ MAX_ROUNDS × 2 swings total),
     * guaranteeing termination regardless of damage output.
     *
     * @return array{hp: array{attacker: int, defender: int}, events: array, winnerKey: string, knockout: bool, rounds: int}
     */
    private function simulate(Character $attacker, Character $defender): array
    {
        $hp = ['attacker' => $attacker->health, 'defender' => $defender->health];
        $fighters = [
            'attacker' => ['stats' => $this->effectiveStats($attacker), 'level' => $attacker->level],
            'defender' => ['stats' => $this->effectiveStats($defender), 'level' => $defender->level],
        ];

        // Turn order: higher effective speed first; exact tie → attacker first.
        // Agility owned this until ADR-003 — initiative is a question of who
        // is quicker, so it re-homed to speed, not to dexterity.
        $order = $fighters['attacker']['stats']['speed'] >= $fighters['defender']['stats']['speed']
            ? ['attacker', 'defender']
            : ['defender', 'attacker'];

        $events = [];
        $winnerKey = null;
        $knockout = false;
        $rounds = 0;

        for ($round = 1; $round <= self::MAX_ROUNDS; $round++) {
            $rounds = $round;

            foreach ($order as $actorKey) {
                $targetKey = $actorKey === 'attacker' ? 'defender' : 'attacker';

                $events[] = $this->resolveTurn($round, $actorKey, $targetKey, $fighters, $hp);

                if ($hp[$targetKey] <= 0) {
                    $winnerKey = $actorKey;
                    $knockout = true;
                    break 2;
                }
            }
        }

        if ($winnerKey === null) {
            // No KO within MAX_ROUNDS: higher remaining HP wins; exact tie → defender (ADR-001).
            $winnerKey = $hp['attacker'] > $hp['defender'] ? 'attacker' : 'defender';
        }

        return compact('hp', 'events', 'winnerKey', 'knockout', 'rounds');
    }

    /**
     * Resolve one actor's turn against the target, mutating $hp in place.
     *
     * @param  array{attacker: array{stats: array, level: int}, defender: array{stats: array, level: int}}  $fighters
     * @param  array{attacker: int, defender: int}  &$hp
     * @return array{round: int, actor: string, missed: bool, dodged: bool, damage: int, target_hp: int}
     */
    private function resolveTurn(int $round, string $actorKey, string $targetKey, array $fighters, array &$hp): array
    {
        $actor = $fighters[$actorKey];
        $target = $fighters[$targetKey];

        // ADR-003: miss is rolled FIRST — a swing that never connects costs the
        // defender no dodge roll. The `> 0` guard is not an optimisation: a 0%
        // chance can never fire, and skipping the draw keeps the RNG stream
        // identical for equal-speed fights (every pre-ADR-003 seeded test).
        $miss = $this->effectiveMissChance($actor['stats']['speed'], $target['stats']['speed']);

        if ($miss > 0 && $this->rng->getInt(1, 100) <= $miss) {
            return [
                'round' => $round,
                'actor' => $actorKey,
                'missed' => true,
                'dodged' => false,
                'damage' => 0,
                'target_hp' => max(0, $hp[$targetKey]),
            ];
        }

        $dodge = $this->effectiveDodgeChance($target['stats']['dexterity']);

        if ($this->rng->getInt(1, 100) <= $dodge) {
            return [
                'round' => $round,
                'actor' => $actorKey,
                'missed' => false,
                'dodged' => true,
                'damage' => 0,
                'target_hp' => max(0, $hp[$targetKey]),
            ];
        }

        $damage = max(
            self::MIN_DAMAGE,
            $actor['stats']['strength'] - $target['stats']['defense'] + $this->rng->getInt(-$actor['level'], $actor['level'])
        );
        $hp[$targetKey] -= $damage;

        return [
            'round' => $round,
            'actor' => $actorKey,
            'missed' => false,
            'dodged' => false,
            'damage' => $damage,
            'target_hp' => max(0, $hp[$targetKey]),
        ];
    }

    /**
     * Persist every consequence of the sim (same transaction as the caller)
     * and write the immutable combat_logs row.
     *
     * @param  array{hp: array{attacker: int, defender: int}, events: array, winnerKey: string, knockout: bool, rounds: int}  $sim
     */
    private function persistOutcome(Character $attacker, Character $defender, array $sim): CombatResult
    {
        ['hp' => $hp, 'events' => $events, 'winnerKey' => $winnerKey, 'knockout' => $knockout, 'rounds' => $rounds] = $sim;

        // Snapshot effective stats + starting HP for the immutable log, before mutating health below.
        $attackerSnapshot = $this->effectiveStats($attacker) + ['health' => $attacker->health];
        $defenderSnapshot = $this->effectiveStats($defender) + ['health' => $defender->health];

        $attacker->health = max(0, $hp['attacker']);
        $defender->health = max(0, $hp['defender']);

        // Committing to a fight ends the search that led to it, so the next
        // reveal is free again (OpponentService prices off these two columns).
        // Done here rather than in the caller because resolve() is the single
        // entry point to combat — every path in gets the reset. Only the
        // attacker's search resets: the defender never chose this fight.
        $attacker->opponent_id = null;
        $attacker->opponent_rerolls = 0;

        $winner = $winnerKey === 'attacker' ? $attacker : $defender;
        $loser = $winnerKey === 'attacker' ? $defender : $attacker;

        $loser->hospitalized_until = now()->addMinutes(self::HOSPITAL_MINUTES);

        $stolen = (int) floor($loser->gold * self::GOLD_STEAL_PCT);
        $loser->gold -= $stolen;
        $winner->gold += $stolen;

        $attacker->save();
        $defender->save();

        $xp = self::XP_BASE + $loser->level * self::XP_PER_LEVEL;
        if ($winner->level > $loser->level + self::FARM_GAP) {
            $xp = (int) floor($xp / 2);
        }

        $levelResult = app(LevelingService::class)->awardXp($winner, $xp);

        $attackerWon = $winnerKey === 'attacker';
        $goldChange = $attackerWon ? $stolen : -$stolen;
        $xpChange = $attackerWon ? $xp : 0;

        CombatLog::create([
            'attacker_id' => $attacker->id,
            'defender_id' => $defender->id,
            'attacker_level' => $attacker->level,
            'defender_level' => $defender->level,
            'attacker_stats' => $attackerSnapshot,
            'defender_stats' => $defenderSnapshot,
            'events' => $events,
            'winner_id' => $winner->id,
            'gold_change' => $goldChange,
            'xp_change' => $xpChange,
        ]);

        return new CombatResult(
            winner: $winner,
            loser: $loser,
            attacker: $attacker,
            defender: $defender,
            events: $events,
            goldChange: $goldChange,
            xpChange: $xpChange,
            leveledUp: $levelResult['leveled_up'],
            rounds: $rounds,
            knockout: $knockout,
        );
    }
}
