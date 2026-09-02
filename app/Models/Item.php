<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    /** @use HasFactory<\Database\Factories\ItemFactory> */
    use HasFactory;

    /**
     * Equipment slots (ADR-003). One equipped item per slot — MarketService
     * unequips the current occupant before equipping a new one.
     *
     * `body` is a full worn suit (chest, arms, legs, feet abstracted into one
     * piece); `shield` is the carried defensive implement and the only slot
     * that carries a built-in mobility penalty.
     */
    public const SLOTS = ['weapon', 'shield', 'head', 'body'];

    /** The three slots that exist to contribute defense (i.e. everything but the weapon). */
    public const ARMOR_SLOTS = ['shield', 'head', 'body'];

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array<int, string>
     */
    protected $guarded = [];
}
