{{--
    Public landing page. Generic dark-medieval skin only — warring kingdoms in
    the abstract. Factions are deliberately unnamed and unshown here: display
    naming is still undecided (CLAUDE.md), and the pick belongs to character
    creation, not the pitch.
--}}
@php
    // Named $coreLoop, not $loop — Blade owns $loop inside @foreach.
    // Icons match the ones Home already uses for the same four actions.
    $coreLoop = [
        ['icon' => 'fa-hammer', 'label' => __('Work'), 'blurb' => __('Spend thy energy at an honest trade and be paid in gold.')],
        ['icon' => 'fa-dumbbell', 'label' => __('Train'), 'blurb' => __('Drill strength, defence, speed and dexterity until they hold under load.')],
        ['icon' => 'fa-swords', 'label' => __('Battle'), 'blurb' => __('Seek a mark and settle it — thy steel against theirs, resolved at once.')],
        ['icon' => 'fa-arrow-up-right-dots', 'label' => __('Level Up'), 'blurb' => __('Take the experience off the field and come back the harder man.')],
    ];
@endphp

<x-landing-layout>
    {{-- ── Hero ──────────────────────────────────────────────────────────── --}}
    <section class="relative flex min-h-[80vh] items-center justify-center bg-cover bg-center px-4 py-24"
             style="background-image: url('{{ asset('images/hero.png') }}');">
        {{-- The siege art is a busy raster — without this wash it eats the copy.
             It also fades into the page's black so the seam doesn't show.

             Weighted for the narrow case, and it has to be: bg-cover zooms hard
             on a phone and parks the headline squarely on the art's sunset, the
             single brightest thing in it — gold-on-orange with no wash is
             unreadable. Desktop shows enough of the dark flanks to take the
             lighter sm: values, which is the only reason the art reads there at
             all. The radial eases off with it, concentrating what is left on
             the copy rather than crushing the already-black edges. --}}
        <div class="absolute inset-0 bg-gradient-to-b from-black/85 via-black/80 to-black sm:from-black/70 sm:via-black/75"></div>
        <div class="absolute inset-0 opacity-100 sm:opacity-70"
             style="background: radial-gradient(ellipse at 50% 45%, rgba(0,0,0,.6) 0%, rgba(0,0,0,.3) 55%, rgba(0,0,0,0) 100%);"></div>

        <div class="relative mx-auto max-w-3xl text-center">
            <x-application-logo class="mx-auto h-40 w-40" />

            <x-chain-divider class="my-6" />

            <h1 class="font-uncialAntiqua text-4xl text-yellow-500 text-shadow-lg shadow-yellow-600 sm:text-5xl">
                {{ __('Two banners. One field. No quarter.') }}
            </h1>

            <p class="mx-auto mt-6 max-w-xl font-sans text-base text-stone-300">
                {{ __('Rival kingdoms have bled this land white and neither will yield it. Swear to a banner, work for coin, drill until thy arms ache, and settle the rest in the ring.') }}
            </p>

            <div class="mt-10 flex flex-col items-center justify-center gap-5 sm:flex-row sm:gap-8">
                @auth
                    <a href="{{ route('home') }}" wire:navigate>
                        <x-button type="button" class="px-8 py-3 text-lg">
                            <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Continue') }}
                        </x-button>
                    </a>
                @else
                    @if (Route::has('register'))
                        <a href="{{ route('register') }}" wire:navigate>
                            <x-button type="button" class="px-8 py-3 text-lg">
                                <i class="fa-duotone fa-solid fa-flag-swallowtail me-2"></i>{{ __('Start Thy Legend') }}
                            </x-button>
                        </a>
                    @endif

                    <a href="{{ route('login') }}" wire:navigate
                       class="font-newRocker uppercase tracking-widest text-yellow-500 underline underline-offset-4 transition hover:text-yellow-300">
                        {{ __('Already sworn? Log in') }}
                    </a>
                @endauth
            </div>
        </div>
    </section>

    <x-divider />

    {{-- ── Core loop ─────────────────────────────────────────────────────── --}}
    <section class="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
        <h2 class="text-center font-uncialAntiqua text-3xl text-yellow-500 text-shadow shadow-yellow-700">
            {{ __('The Long Grind') }}
        </h2>

        <p class="mx-auto mt-4 max-w-2xl text-center font-sans text-sm text-stone-300">
            {{ __('No quests, no heroes, no chosen one. Four things, repeated, until thou art the one worth fearing.') }}
        </p>

        <div class="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($coreLoop as $step)
                <x-dark-leather class="border border-yellow-700/60 px-6 py-8 text-center">
                    <i class="fa-duotone fa-solid {{ $step['icon'] }} fa-3x text-yellow-500"></i>
                    <x-label class="mt-4 text-xl text-yellow-500">{{ $step['label'] }}</x-label>
                    <p class="mt-2 font-sans text-sm text-stone-300">{{ $step['blurb'] }}</p>
                </x-dark-leather>
            @endforeach
        </div>
    </section>

    {{-- ── Social proof ──────────────────────────────────────────────────── --}}
    {{-- Null means the strip is switched off in config/game.php, same as
         Home's faction headcount. Real counts or nothing — never a decoration. --}}
    @if ($stats !== null)
        <x-divider />

        <section class="px-4 py-16">
            <div class="mx-auto grid max-w-4xl grid-cols-1 gap-8 text-center sm:grid-cols-3">
                @foreach ([
                    ['icon' => 'fa-users', 'value' => $stats['players'], 'label' => __('Souls enlisted')],
                    ['icon' => 'fa-helmet-battle', 'value' => $stats['characters'], 'label' => __('Warriors afield')],
                    ['icon' => 'fa-swords', 'value' => $stats['battles'], 'label' => __('Battles fought')],
                ] as $stat)
                    <div>
                        <i class="fa-duotone fa-solid {{ $stat['icon'] }} fa-2x text-yellow-500"></i>
                        <x-label class="mt-3 text-4xl text-yellow-500 text-shadow shadow-yellow-700">
                            {{ number_format($stat['value']) }}
                        </x-label>
                        <x-label class="mt-1 text-sm uppercase tracking-widest text-stone-400">
                            {{ $stat['label'] }}
                        </x-label>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <x-divider />

    {{-- ── Closing call to action ────────────────────────────────────────── --}}
    <section class="px-4 py-20 text-center">
        <x-wax-seal :size="64" class="mx-auto block" />

        <h2 class="mt-6 font-uncialAntiqua text-3xl text-yellow-500 text-shadow shadow-yellow-700">
            {{ __('The field does not wait.') }}
        </h2>

        <p class="mx-auto mt-4 max-w-xl font-sans text-sm text-stone-300">
            {{ __('Pick thy banner, take thy first wage, and be someone worth marking on the map.') }}
        </p>

        <div class="mt-8 flex justify-center">
            @auth
                <a href="{{ route('home') }}" wire:navigate>
                    <x-button type="button" class="px-8 py-3 text-lg">
                        <i class="fa-duotone fa-solid fa-swords me-2"></i>{{ __('Continue') }}
                    </x-button>
                </a>
            @elseif (Route::has('register'))
                <a href="{{ route('register') }}" wire:navigate>
                    <x-button type="button" class="px-8 py-3 text-lg">
                        <i class="fa-duotone fa-solid fa-flag-swallowtail me-2"></i>{{ __('Start Thy Legend') }}
                    </x-button>
                </a>
            @endauth
        </div>
    </section>
</x-landing-layout>
