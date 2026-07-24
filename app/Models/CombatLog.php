<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CombatLog extends Model
{
    /**
     * Append-only log: no updated_at column exists (migration is
     * created_at-only via useCurrent()).
     */
    public const UPDATED_AT = null;

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
            'attacker_stats' => 'array',
            'defender_stats' => 'array',
            'events' => 'array',
        ];
    }

    /**
     * The character who initiated the fight.
     */
    public function attacker(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'attacker_id');
    }

    /**
     * The character who was attacked.
     */
    public function defender(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'defender_id');
    }

    /**
     * The character who won the fight (nullable: nullOnDelete if deleted later).
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'winner_id');
    }
}
