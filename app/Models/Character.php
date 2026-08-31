<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    /**
     * Faction keys are deliberately generic: display names are still TBD and
     * must be changeable without touching schema or queries. Picked once at
     * character creation, immutable for MVP.
     */
    public const FACTIONS = ['faction_1', 'faction_2'];

    /**
     * Placeholder display copy. Final naming is deliberately undecided
     * (CLAUDE.md) — the generic keys stay in the DB and these two methods are
     * the only place a faction is worded, so renaming later touches nothing
     * else. Both the faction-select page and Home read from here.
     */
    public static function factionLabel(string $faction): string
    {
        return __('Faction :number', ['number' => str_replace('faction_', '', $faction)]);
    }

    public static function factionDescription(string $faction): string
    {
        return match ($faction) {
            'faction_1' => __('Placeholder copy: a war-host that answers steel with steel. It marches in the open, and its armourers forge wares no other banner may carry.'),
            'faction_2' => __('Placeholder copy: a war-host that answers steel with cunning. It marches unseen, and its armourers forge wares no other banner may carry.'),
            default => '',
        };
    }

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hospitalized_until' => 'datetime',
            'activity_completes_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the character.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the character's owned item rows (see CharacterItem for the item relation).
     */
    public function characterItems(): HasMany
    {
        return $this->hasMany(CharacterItem::class);
    }

    /**
     * A hospitalized character can't attack or be attacked (CombatService
     * pre-check, ADR-001 §Hospital). Lazy time check, no scheduler: release
     * is implicit once hospitalized_until passes.
     */
    public function isHospitalized(): bool
    {
        return $this->hospitalized_until !== null && $this->hospitalized_until->isFuture();
    }

    /**
     * A busy character is mid Train/Work session and is locked out of
     * starting another one, of attacking, and of the market (ADR-002 §4).
     * Being attacked is NOT blocked (ADR-002 fork 1).
     *
     * Pure time comparison, same shape as isHospitalized(). Callers resolve
     * any pending activity first, then check this — once completes_at passes
     * this is already false while activity_type is still set, so the resolve
     * clears the session and the action proceeds in the same request.
     */
    public function isBusy(): bool
    {
        return $this->activity_completes_at !== null && $this->activity_completes_at->isFuture();
    }
}
