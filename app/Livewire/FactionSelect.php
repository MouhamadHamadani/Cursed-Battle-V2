<?php

namespace App\Livewire;

use App\Models\Character;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Step two of character creation: /register makes the account, this page
 * forges the character and stamps its faction.
 *
 * Browsing is non-committal — opening a faction writes nothing. Only
 * confirm() creates the character, and the pick is immutable from then on
 * (CLAUDE.md: characters.faction, picked once, no switching in MVP).
 */
#[Layout('layouts.app')]
class FactionSelect extends Component
{
    /** Faction key shown in the preview modal. Untrusted — re-checked in confirm(). */
    public ?string $preview = null;

    /**
     * Presentational only: <x-dark-modal> entangles a boolean, so the open
     * state can't live on $preview itself (the same split Battle uses for
     * its result modal).
     */
    public bool $showPreview = false;

    /**
     * Already forged? The pick is permanent, so there is nothing to show.
     *
     * A full page load, not wire:navigate: layouts.app @persist's the status
     * bar, and the instance rendered here has no character to show. Only a
     * fresh document rebuilds it.
     */
    public function mount()
    {
        if (auth()->user()->character) {
            return $this->redirectRoute('home');
        }
    }

    /**
      * Named apart from $preview on purpose: Livewire's $wire proxy resolves a
      * property before a method, so an action sharing a property's name is
      * unreachable from the browser (and PHP-level tests never notice).
      */
    public function previewFaction(string $faction): void
    {
        $this->preview = $faction;
        $this->showPreview = true;
    }

    public function confirm()
    {
        abort_unless(in_array($this->preview, Character::FACTIONS, true), 422);

        // firstOrCreate, not create: the relation is constrained to this user
        // and user_id is unique, so a double submit (or a race with a second
        // tab) resolves to the character that already exists instead of
        // throwing — and an existing pick is never overwritten.
        auth()->user()->character()->firstOrCreate([], ['faction' => $this->preview]);

        return $this->redirectRoute('home');
    }

    public function render()
    {
        return view('livewire.faction-select');
    }
}
