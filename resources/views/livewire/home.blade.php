<div>
    @if ($character)
        <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-8">
        <x-iron-scrollwork>
        <x-dark-leather class="border border-yellow-700 p-6">
        {{-- One rhythm for the sheet rather than a per-block mb-*: the gaps were
             6/8/8/none and read as uneven, which is the kind of thing a player
             feels without being able to name. --}}
        <div class="space-y-8">
        @php
            $xpThreshold = app(\App\Services\LevelingService::class)->threshold($character->level);

            // Refill clocks. Delegated whole, same as the XP threshold above —
            // the tick interval and the cron allowance are RegenService's.
            // Null on either means that bar is already full, so no clock.
            $regen = app(\App\Services\RegenService::class);
            $healthFullAt = $regen->healthFullAt($character);
            $energyFullAt = $regen->energyFullAt($character);
            $pct = fn (int $value, int $max) => $max > 0 ? min(100, max(0, round($value / $max * 100))) : 0;

            // How many of the ten rivets are lit. ceil, not round, so any
            // non-zero value lights at least one — a bar reading fully empty
            // while the figure beside it says 4/100 would be a lie.
            $lit = fn (int $value, int $max) => (int) ceil($pct($value, $max) / 10);

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
            <x-dark-wall class="border border-red-900 p-4 text-center">
                <x-label class="text-2xl text-red-500">
                    <i class="fa-duotone fa-solid fa-kit-medical me-2"></i>
                    {{ __('In hospital — released :time.', ['time' => $character->hospitalized_until->diffForHumans()]) }}
                </x-label>
            </x-dark-wall>
        @endif

        @if ($busy)
            {{-- Gold, not red: a session in progress is the character working
                 as intended, not a punishment. Hospital keeps the red. --}}
            <x-dark-wall class="border border-yellow-700 p-4 text-center">
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

        {{-- Hero: rank is the sheet's focal point, so Level leaves the stat
             grid below and becomes a medallion. --}}
        <div class="flex flex-col items-center gap-5 sm:flex-row sm:gap-8">
            {{-- The shared <x-wax-seal> at medallion scale with two ribbon tails
                 hung behind it. Single use, so the tails are inline markup and
                 not a second component. The number sits BESIDE the seal rather
                 than inside it: the seal's centre is its crossed blades, and a
                 numeral laid over them is unreadable at any size. --}}
            {{-- pb-6 on the outer box reserves the hanging tails' overflow, so
                 the row below is not overlapped. The tails anchor to top-full
                 (the seal's own bottom edge) and are pulled back up behind the
                 disc — anchoring them to the padded box instead leaves them
                 floating clear of the seal as two detached shields. --}}
            <div class="shrink-0 pb-6">
                <div class="relative">
                    <x-wax-seal :size="104" class="relative z-10 block" />
                    <div aria-hidden="true" class="absolute inset-x-0 top-full z-0 flex -translate-y-4 justify-center gap-5">
                        <span class="h-10 w-7 -rotate-12 bg-gradient-to-b from-red-900 to-black [clip-path:polygon(0_0,100%_0,100%_62%,50%_100%,0_62%)]"></span>
                        <span class="h-10 w-7 rotate-12 bg-gradient-to-b from-red-900 to-black [clip-path:polygon(0_0,100%_0,100%_62%,50%_100%,0_62%)]"></span>
                    </div>
                </div>
            </div>

            <div class="text-center sm:flex-1 sm:text-start">
                <x-label class="text-sm uppercase tracking-widest text-yellow-600">{{ __('Level') }}</x-label>
                <x-label class="font-uncialAntiqua text-5xl">{{ $character->level }}</x-label>
            </div>

            {{-- Faction crest. Same action and the same modal as the plain bar it
                 replaces — only the silhouette changed. Still deliberately
                 wordless beyond the placeholder label: naming the factions is
                 an open decision, not this pass's to make.

                 The notch is cut on BOTH layers, an outer gold sheet and an
                 inner panel inset by a single pixel of padding. A border cannot
                 be used here: clip-path crops the border with the box, so the
                 diagonal edges would come out bare. The focus ring stays on the
                 unclipped <button>, so it is never cropped either. --}}
            <button type="button" wire:click="$set('showFaction', true)"
                    class="group w-full shrink-0 focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500 sm:w-auto">
                <span class="block bg-yellow-700 p-px transition duration-300 group-hover:bg-yellow-500 group-focus-visible:bg-yellow-500 [clip-path:polygon(0_0,100%_0,100%_78%,50%_100%,0_78%)]">
                    <x-dark-wall class="flex items-center justify-center gap-3 px-6 pb-8 pt-4 [clip-path:polygon(0_0,100%_0,100%_78%,50%_100%,0_78%)]">
                        <i class="fa-duotone fa-solid fa-flag-swallowtail text-yellow-500" aria-hidden="true"></i>
                        <span class="text-start">
                            <x-label class="text-xs uppercase tracking-widest text-yellow-600">{{ __('Faction') }}</x-label>
                            <x-label class="text-lg">{{ \App\Models\Character::factionLabel($character->faction) }}</x-label>
                        </span>
                    </x-dark-wall>
                </span>
            </button>
        </div>

        <x-gem-divider small />

        {{-- Vitals: the three bars a fighter reads first. --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-dark-wall class="relative border border-yellow-700 p-4">
                {{-- Same corner flourish as the sheet's outer scrollwork and the
                     town-map plaques, scaled to a single card. Rendered first with
                     the content stacked over it, as <x-brass-corners> requires. --}}
                <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                <div class="relative flex items-center justify-between mb-2">
                    <x-label class="text-yellow-500">
                        <i class="fa-duotone fa-solid fa-star me-2"></i>{{ __('XP') }}
                    </x-label>
                    <x-label>{{ $character->xp }} / {{ $xpThreshold }}</x-label>
                </div>
                {{-- Riveted fill: ten segments with a hairline gap, so the bar
                     reads as banded metal rather than a painted rect. Still
                     aria-hidden — the figure above it is the accessible source. --}}
                <div class="relative flex h-4 gap-0.5 border border-yellow-700 bg-black p-px" aria-hidden="true">
                    @for ($i = 0; $i < 10; $i++)
                        <span @class(['flex-1', 'bg-yellow-500' => $i < $lit($character->xp, $xpThreshold)])></span>
                    @endfor
                </div>
            </x-dark-wall>

            <x-dark-wall class="relative border border-yellow-700 p-4">
                {{-- Same corner flourish as the sheet's outer scrollwork and the
                     town-map plaques, scaled to a single card. Rendered first with
                     the content stacked over it, as <x-brass-corners> requires. --}}
                <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                <div class="relative flex items-center justify-between mb-2">
                    <x-label class="text-red-500">
                        <i class="fa-duotone fa-solid fa-heart me-2"></i>{{ __('Health') }}
                    </x-label>
                    <x-label>{{ $character->health }} / {{ $character->max_health }}</x-label>
                </div>
                {{-- Riveted fill: ten segments with a hairline gap, so the bar
                     reads as banded metal rather than a painted rect. Still
                     aria-hidden — the figure above it is the accessible source. --}}
                <div class="relative flex h-4 gap-0.5 border border-yellow-700 bg-black p-px" aria-hidden="true">
                    @for ($i = 0; $i < 10; $i++)
                        <span @class(['flex-1', 'bg-red-500' => $i < $lit($character->health, $character->max_health)])></span>
                    @endfor
                </div>
                {{-- A second clock alongside the status bar's, same shape as the
                     session countdown above: both dispatch character-updated at
                     zero and the listeners are idempotent, so the duplicate is
                     harmless. Omitted entirely on a full bar. --}}
                @if ($healthFullAt)
                    <p class="mt-2 font-sans text-xs text-stone-400">
                        {{ __('Full in') }}
                        <x-activity-countdown :completes-at="$healthFullAt" event="character-updated" class="text-yellow-600" />
                    </p>
                @endif
            </x-dark-wall>

            <x-dark-wall class="relative border border-yellow-700 p-4">
                {{-- Same corner flourish as the sheet's outer scrollwork and the
                     town-map plaques, scaled to a single card. Rendered first with
                     the content stacked over it, as <x-brass-corners> requires. --}}
                <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                <div class="relative flex items-center justify-between mb-2">
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
                {{-- Riveted fill: ten segments with a hairline gap, so the bar
                     reads as banded metal rather than a painted rect. Still
                     aria-hidden — the figure above it is the accessible source. --}}
                <div class="relative flex h-4 gap-0.5 border border-yellow-700 bg-black p-px" aria-hidden="true">
                    @for ($i = 0; $i < 10; $i++)
                        <span @class(['flex-1', 'bg-yellow-700' => $i < $lit($character->energy, $character->max_energy)])></span>
                    @endfor
                </div>
                @if ($energyFullAt)
                    <p class="mt-2 font-sans text-xs text-stone-400">
                        {{ __('Full in') }}
                        <x-activity-countdown :completes-at="$energyFullAt" event="character-updated" class="text-yellow-600" />
                    </p>
                @endif
                {{-- Kept below the clocks: they say when, this says why. --}}
                <p class="mt-2 font-sans text-xs text-stone-400">{{ __('Health and energy return on the world tick.') }}</p>
            </x-dark-wall>
        </div>

        <x-gem-divider small />

        {{-- Standing and stats.

             Icons are new here — the row had none. Two of the five diverge from
             Train's table on purpose:
               Speed  -> fa-wind, not Train's fa-bolt, because fa-bolt is Energy
                         in the vitals band directly above and in the status
                         strip above that. Train should follow in its own pass.
               Strength -> fa-hand-fist, matching Train, rather than fa-sword,
                         which would put a second sword glyph a few hundred
                         pixels from the Battle card's fa-swords.
             Dexterity keeps Train's fa-feather: ADR-003 gives dodge to
             dexterity and hit chance to speed, so an accuracy glyph here would
             point at the wrong stat. --}}
        @php
            $stats = [
                ['label' => __('Gold'), 'icon' => 'fa-coins', 'value' => $character->gold],
                ['label' => __('Strength'), 'icon' => 'fa-hand-fist', 'value' => $character->strength],
                ['label' => __('Defense'), 'icon' => 'fa-shield-halved', 'value' => $character->defense],
                ['label' => __('Speed'), 'icon' => 'fa-wind', 'value' => $character->speed],
                ['label' => __('Dexterity'), 'icon' => 'fa-feather', 'value' => $character->dexterity],
            ];
        @endphp

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-6">
            @foreach ($stats as $stat)
                {{-- Full yellow-700, not the /60 the concept used to make these
                     recede: measured against the dark_wall texture the faded
                     border is 1.98:1, under the 3:1 a UI boundary owes. The
                     recession comes from cell size and the smaller roundel
                     instead. --}}
                <x-dark-wall class="relative border border-yellow-700 p-4">
                    <x-brass-corners class="h-3 w-3 text-yellow-700/70" />
                    <div class="relative flex flex-col items-center text-center">
                        <x-icon-roundel :icon="$stat['icon']" />
                        <x-label class="mt-2 text-xs uppercase tracking-widest text-yellow-600">{{ $stat['label'] }}</x-label>
                        <x-label class="text-3xl">{{ $stat['value'] }}</x-label>
                    </div>
                </x-dark-wall>
            @endforeach
        </div>

        </div>
        </x-dark-leather>
        </x-iron-scrollwork>

        <x-gem-divider class="mx-auto max-w-2xl" />

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
            // Two values rather than one predicate with a flag, so each card
            // says at its own line which rule closes it. Busy outranks hospital
            // on Battle because it is the broader lock.
            $closed = $busy ? 'busy' : null;
            $closedForCombat = $closed ?? ($hospitalized ? 'hospital' : null);

            $links = [
                ['route' => 'work', 'label' => __('Work'), 'icon' => 'fa-hammer', 'blurb' => __('Trade energy for gold.'), 'blocked' => $closed],
                ['route' => 'train', 'label' => __('Train'), 'icon' => 'fa-dumbbell', 'blurb' => __('Sharpen thy stats.'), 'blocked' => $closed],
                ['route' => 'market', 'label' => __('Market'), 'icon' => 'fa-treasure-chest', 'blurb' => __('Arm and armour thyself.'), 'blocked' => $closed],
                ['route' => 'battle', 'label' => __('Battle'), 'icon' => 'fa-swords', 'blurb' => __('Test thy steel on another.'), 'blocked' => $closedForCombat],
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
                   class="group block focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500">
                    <x-dark-wall @class([
                        'relative h-full border p-6 text-center transition duration-300',
                        'border-yellow-700 group-hover:-translate-y-1 group-hover:border-yellow-500 group-focus-visible:border-yellow-500' => ! $link['blocked'],
                        // No hover promise on a blocked card — it neither lifts nor brightens.
                        'border-stone-700' => (bool) $link['blocked'],
                    ])>
                        {{-- Both colours written out literally: <x-brass-corners>
                             reads its class through a prop, and Tailwind only
                             builds classes it can see in the calling file. --}}
                        <x-brass-corners class="h-3 w-3 {{ $link['blocked'] ? 'text-stone-700' : 'text-yellow-700/70' }}" />
                        <x-icon-roundel class="relative" size="lg" :icon="$link['icon']" :muted="(bool) $link['blocked']" />
                        <x-label @class(['relative text-2xl mt-3', 'text-stone-400' => (bool) $link['blocked']])>{{ $link['label'] }}</x-label>

                        {{-- The evergreen blurb gives way to the reason: at two
                             columns wide there is room for one line, and which
                             door is shut right now beats what is behind it. --}}
                        <p class="relative mt-1 font-sans text-xs text-stone-400">
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
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <x-label class="text-2xl text-red-500 text-center">{{ __('No character found.') }}</x-label>
            </div>
        </div>
    @endif
</div>
