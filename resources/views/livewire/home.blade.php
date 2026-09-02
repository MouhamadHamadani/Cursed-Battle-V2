<div>
    @if ($character)
        <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
        <x-iron-scrollwork>
        <x-dark-leather class="border border-yellow-700 p-6">
        @php
            $xpThreshold = app(\App\Services\LevelingService::class)->threshold($character->level);
            $pct = fn (int $value, int $max) => $max > 0 ? min(100, max(0, round($value / $max * 100))) : 0;

            // Read once here, used by the banners below and by the quick-link
            // cards at the foot of the page. Both are the model's own time
            // comparisons — this view decides nothing, it only renders what the
            // services already enforce.
            $busy = $character->isBusy();
            $hospitalized = $character->isHospitalized();
        @endphp

        {{-- Status banners. The strip above already badges both states on every
             page; these say the same thing louder because Home is where a
             player stops to read, and they pair with the card states below —
             the banner says what is true and until when, the cards say which of
             the four options it closes. Both can be true at once. --}}
        @if ($hospitalized)
            <x-dark-wall class="border border-red-900 p-4 mb-6 text-center">
                <x-label class="text-2xl text-red-500">
                    <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>
                    {{ __('In hospital — released :time.', ['time' => $character->hospitalized_until->diffForHumans()]) }}
                </x-label>
            </x-dark-wall>
        @endif

        @if ($busy)
            {{-- Gold, not red: a session in progress is the character working
                 as intended, not a punishment. Hospital keeps the red. --}}
            <x-dark-wall class="border border-yellow-700 p-4 mb-6 text-center">
                <x-label class="text-2xl text-yellow-500">
                    <i class="fa-duotone fa-solid {{ $character->activity_type === 'work' ? 'fa-hammer' : 'fa-dumbbell' }} me-2"></i>
                    {{ ucfirst(\App\Services\ActivityService::describe($character->activity_type)) }}
                </x-label>
                {{-- A second countdown alongside the status bar's is the shape
                     <x-activity-countdown> was built for, and resolvePending()
                     is documented idempotent for exactly this reason. --}}
                <x-label class="text-4xl mt-2">
                    <x-activity-countdown :completes-at="$character->activity_completes_at" />
                </x-label>
            </x-dark-wall>
        @endif

        {{-- Vitals: the three bars a fighter reads first. --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <x-label class="text-yellow-500">
                        <i class="fa-duotone fa-solid fa-star me-2"></i>{{ __('XP') }}
                    </x-label>
                    <x-label>{{ $character->xp }} / {{ $xpThreshold }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700" aria-hidden="true">
                    <div class="h-full bg-yellow-500" style="width: {{ $pct($character->xp, $xpThreshold) }}%"></div>
                </div>
            </x-dark-wall>

            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    <x-label class="text-red-500">
                        <i class="fa-duotone fa-solid fa-heart me-2"></i>{{ __('Health') }}
                    </x-label>
                    <x-label>{{ $character->health }} / {{ $character->max_health }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700" aria-hidden="true">
                    <div class="h-full bg-red-500" style="width: {{ $pct($character->health, $character->max_health) }}%"></div>
                </div>
            </x-dark-wall>

            <x-dark-wall class="border border-yellow-700 p-4">
                <div class="flex items-center justify-between mb-2">
                    {{-- yellow-600, not the yellow-700 used for the bar fill
                         and the status-bar bolt: measured against the dark_wall
                         texture, 700 is 3.23:1 — fine for a border or a fill,
                         under the 4.5:1 body-text bar. 600 is 5.42:1 and is
                         already in the palette (scrollwork, chain, corners). --}}
                    <x-label class="text-yellow-600">
                        <i class="fa-duotone fa-solid fa-bolt me-2"></i>{{ __('Energy') }}
                    </x-label>
                    <x-label>{{ $character->energy }} / {{ $character->max_energy }}</x-label>
                </div>
                <div class="h-3 bg-black border border-yellow-700" aria-hidden="true">
                    <div class="h-full bg-yellow-700" style="width: {{ $pct($character->energy, $character->max_energy) }}%"></div>
                </div>
                <p class="mt-2 font-sans text-xs text-stone-400">{{ __('Health and energy return on the world tick.') }}</p>
            </x-dark-wall>
        </div>

        {{-- Faction. Click through for the banner's own page. --}}
        <button type="button" wire:click="$set('showFaction', true)"
                class="group block w-full mb-8 focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500">
            <x-dark-wall class="border border-yellow-700 p-4 flex items-center justify-center gap-3 transition duration-300 group-hover:border-yellow-500 group-focus-visible:border-yellow-500">
                <i class="fa-duotone fa-solid fa-flag-swallowtail text-yellow-500"></i>
                <x-label class="text-yellow-500">{{ __('Faction') }}</x-label>
                <x-label class="text-xl">{{ \App\Models\Character::factionLabel($character->faction) }}</x-label>
            </x-dark-wall>
        </button>

        {{-- Standing and stats. --}}
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-6 text-center">
            {{-- Rank badge. Sized to the text-3xl line box on purpose so the
                 seal sits beside the number without making this one cell taller
                 than the four next to it. --}}
            <div>
                <x-label class="text-yellow-500">{{ __('Level') }}</x-label>
                <div class="flex items-center justify-center gap-2">
                    <x-wax-seal :size="36" />
                    <x-label class="text-3xl">{{ $character->level }}</x-label>
                </div>
            </div>
            <div>
                <x-label class="text-yellow-500">
                    <i class="fa-duotone fa-solid fa-coins me-2"></i>{{ __('Gold') }}
                </x-label>
                <x-label class="text-3xl">{{ $character->gold }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Strength') }}</x-label>
                <x-label class="text-3xl">{{ $character->strength }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Defense') }}</x-label>
                <x-label class="text-3xl">{{ $character->defense }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Speed') }}</x-label>
                <x-label class="text-3xl">{{ $character->speed }}</x-label>
            </div>
            <div>
                <x-label class="text-yellow-500">{{ __('Dexterity') }}</x-label>
                <x-label class="text-3xl">{{ $character->dexterity }}</x-label>
            </div>
        </div>

        </x-dark-leather>
        </x-iron-scrollwork>

        {{-- Quick links to the four places a character can spend a turn.

             This is what keeps the cards from being four more town-map markers:
             the map is navigation and cannot know the character's state, while
             these show which of the four are currently closed and why. The two
             rules are read off the model, not decided here —
               busy         -> all four (ADR-002 §4)
               hospitalized -> Battle only; hospital blocks combat and nothing
                               else, Work/Train/Market stay open (ADR-001)
             Blocked cards stay links on purpose: Work and Train are where the
             countdown and the in-character copy live, so a busy player heading
             there is going somewhere useful. --}}
        @php
            $blocked = fn (bool $whenHospitalized = false) => $busy ? 'busy' : (($whenHospitalized && $hospitalized) ? 'hospital' : null);
            $links = [
                ['route' => 'work', 'label' => __('Work'), 'icon' => 'fa-hammer', 'blurb' => __('Trade energy for gold.'), 'blocked' => $blocked()],
                ['route' => 'train', 'label' => __('Train'), 'icon' => 'fa-dumbbell', 'blurb' => __('Sharpen thy stats.'), 'blocked' => $blocked()],
                ['route' => 'market', 'label' => __('Market'), 'icon' => 'fa-treasure-chest', 'blurb' => __('Arm and armour thyself.'), 'blocked' => $blocked()],
                ['route' => 'battle', 'label' => __('Battle'), 'icon' => 'fa-swords', 'blurb' => __('Test thy steel on another.'), 'blocked' => $blocked(true)],
            ];
        @endphp

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach ($links as $link)
                {{-- The hover affordance used to sit on the inner panel with
                     nothing at all on the anchor, so a keyboard user got no
                     feedback. Border highlight via `group`, as the town-map
                     markers do it; the ring sits on the anchor itself, as the
                     Town button does it, which is also the element the outline
                     was suppressed on. The ring is outside the blocked branches
                     so focus stays visible on a demoted card too. --}}
                <a href="{{ route($link['route']) }}" wire:navigate
                   class="group focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500">
                    <x-dark-wall @class([
                        'h-full border p-6 text-center transition duration-300',
                        'border-yellow-700 group-hover:border-yellow-500 group-focus-visible:border-yellow-500' => ! $link['blocked'],
                        // No hover promise on a blocked card — the border stays put.
                        'border-stone-700' => (bool) $link['blocked'],
                    ])>
                        <i class="fa-duotone fa-solid {{ $link['icon'] }} fa-3x {{ $link['blocked'] ? 'text-stone-600' : 'text-yellow-500' }}"></i>
                        <x-label @class(['text-2xl mt-3', 'text-stone-400' => (bool) $link['blocked']])>{{ $link['label'] }}</x-label>

                        {{-- The evergreen blurb gives way to the reason: at two
                             columns wide there is room for one line, and which
                             door is shut right now beats what is behind it. --}}
                        <p class="mt-1 font-sans text-xs text-stone-400">
                            @if ($link['blocked'] === 'busy')
                                <i class="fa-duotone fa-solid fa-hourglass-half me-1"></i>{{ __('Busy') }}
                            @elseif ($link['blocked'] === 'hospital')
                                <i class="fa-duotone fa-solid fa-kit-medical me-1"></i>{{ __('In hospital') }}
                            @else
                                {{ $link['blurb'] }}
                            @endif
                        </p>
                    </x-dark-wall>
                </a>
            @endforeach
        </div>
        </div>
        </div>

        <x-dark-modal wire:model.live="showFaction" maxWidth="lg">
            <div class="p-8 text-center">
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500">
                    {{ \App\Models\Character::factionLabel($character->faction) }}
                </x-label>

                {{-- Paired with the faction preview modal on the creation page:
                     same panel, same rule. --}}
                <x-chain-divider class="my-5" />

                <p class="font-sans text-sm text-stone-300">
                    {{ \App\Models\Character::factionDescription($character->faction) }}
                </p>

                @if ($factionCount !== null)
                    <div class="mt-6">
                        <x-label class="text-yellow-500">
                            <i class="fa-duotone fa-solid fa-users me-2"></i>{{ __('Soldiers under this banner') }}
                        </x-label>
                        <x-label class="block text-3xl">{{ $factionCount }}</x-label>
                    </div>
                @endif

                <div class="flex justify-center mt-8">
                    <x-button type="button" x-on:click="show = false">{{ __('Close') }}</x-button>
                </div>
            </div>
        </x-dark-modal>
    @else
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <x-label class="text-xl text-red-500 text-center">{{ __('No character found.') }}</x-label>
            </div>
        </div>
    @endif
</div>
