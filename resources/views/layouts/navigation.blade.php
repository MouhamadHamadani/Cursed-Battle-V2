{{--
    Navigation is a town map, not a link bar. The slim strip below keeps only
    what has to be reachable from every page without a detour — the logo, the
    account menu, and the one button that opens the map. The six game locations
    live on the map itself.

    The live XP/level/gold/health/energy figures are NOT here: <livewire:status-bar>
    renders them directly under this bar in layouts/app.blade.php, where it can
    stay a real Livewire component and re-render on 'character-updated'.

    Alpine state is deliberately NOT wrapped in @persist. wire:navigate replaces
    the whole <body>, so this x-data is torn down and rebuilt on every page
    change — which is exactly the reset we want: follow a marker, land on the
    new page with the map already closed. Persisting it would leave the overlay
    stuck open over the page it just navigated to.
--}}
@php
    // Marker positions are percentages of the map image, tuned against the
    // buildings in public/images/map.png itself (1301x733): the red-roof house
    // top-left, the pell yard bottom-left, the canvas stalls mid-centre, the
    // lit forge bottom-centre, the round hut top-right. Retune here — the
    // markers read these, so no coordinate is written inline twice.
    $locations = [
        ['route' => 'home',     'icon' => 'fa-house-chimney',  'label' => __('Home'),     'x' => 13, 'y' => 21],
        ['route' => 'hospital', 'icon' => 'fa-kit-medical',    'label' => __('Hospital'), 'x' => 77, 'y' => 14],
        ['route' => 'market',   'icon' => 'fa-scale-balanced', 'label' => __('Market'),   'x' => 40, 'y' => 53],
        ['route' => 'battle',   'icon' => 'fa-swords',         'label' => __('Battle'),   'x' => 87, 'y' => 57],
        ['route' => 'train',    'icon' => 'fa-dumbbell',       'label' => __('Train'),    'x' => 19, 'y' => 76],
        ['route' => 'work',     'icon' => 'fa-hammer',         'label' => __('Work'),     'x' => 57, 'y' => 83],
    ];
@endphp

@php
    // Opening the map parks the viewport on wherever the player currently is,
    // which is the only part of a panned map they can't find by looking. With
    // no active route (or no overflow to scroll) both branches are harmless.
    $openMap = <<<'JS'
        map = true;
        $nextTick(() => {
            $refs.closeMap.focus();
            const here = $refs.mapScroll.querySelector('[aria-current]');
            if (here) here.scrollIntoView({ block: 'nearest', inline: 'center' });
            else $refs.mapScroll.scrollLeft = ($refs.mapScroll.scrollWidth - $refs.mapScroll.clientWidth) / 2;
        });
    JS;
@endphp

<nav x-data="{ map: false }" @keydown.escape.window="map = false">
    <x-dark-leather class="border-b border-yellow-700">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between gap-4 py-3">
                <!-- Logo -->
                <a href="{{ route('home') }}" wire:navigate title="{{ config('app.name', 'Cursed Battle') }}" class="shrink-0">
                    @persist('logo')
                        <x-application-logo class="block h-12 w-auto" />
                    @endpersist
                </a>

                {{-- One trigger at every breakpoint. The old bar had two nav
                     systems (inline links + a hamburger list) that had to be
                     kept in sync by hand; the map is the only one now. --}}
                <button type="button" @click="{{ $openMap }}"
                        class="inline-flex items-center gap-x-2 border border-yellow-700 px-4 py-2 font-newRocker text-white hover:border-yellow-500 hover:text-yellow-500 focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500 transition duration-300 ease-in-out">
                    <i class="fa-duotone fa-solid fa-map text-yellow-500"></i>
                    {{ __('Town') }}
                </button>

                <!-- Account menu -->
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent font-newRocker text-white hover:text-yellow-500 focus:outline-none transition ease-in-out duration-300">
                            <i class="fa-duotone fa-solid fa-user text-yellow-500"></i>
                            <span class="hidden sm:inline ms-2">{{ Auth::user()->name }}</span>
                            <i class="fa-duotone fa-solid fa-chevron-down ms-2 text-yellow-500"></i>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')" wire:navigate>
                            {{ __('Profile') }}
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>
        </div>
    </x-dark-leather>

    {{--
        Town map overlay.

        The fade is a plain CSS animation, NOT x-transition. Declaring any
        x-transition routes BOTH directions through Alpine's transition system,
        which defers the hide behind requestAnimationFrame — and the embedded
        preview pane never delivers that frame, so a closed overlay stays
        painted over the page and reads as stuck open. Here x-show toggles
        `display` synchronously and the keyframe only decorates the show, which
        behaves the same in a real browser and cannot wedge.
    --}}
    {{-- Scroll lock is done by hand, NOT x-trap.noscroll: this Livewire build's
         bundled Alpine ships without the focus plugin, so x-trap is an unknown
         directive that Alpine silently ignores — it looks like a lock and does
         nothing. x-effect re-runs on init, so if a marker is followed while the
         map is open the new page clears the lock on its own. --}}
    <div x-show="map" x-cloak
         x-effect="document.body.style.overflow = map ? 'hidden' : ''"
         role="dialog" aria-modal="true" aria-label="{{ __('Town map') }}"
         class="fixed inset-0 z-50 flex flex-col motion-safe:animate-fade-in">

        {{-- Same vignette treatment as the auth funnel: flat black would make
             the map float in a void, the radial pulls its edges down instead. --}}
        <div class="absolute inset-0 bg-black"></div>
        <div class="absolute inset-0"
             style="background: radial-gradient(ellipse at 50% 50%, rgba(0,0,0,.35) 0%, rgba(0,0,0,.85) 70%, rgba(0,0,0,.98) 100%);"></div>

        <button type="button" x-ref="closeMap" @click="map = false" aria-label="{{ __('Close map') }}"
                class="absolute top-4 end-4 z-10 flex h-11 w-11 items-center justify-center border border-yellow-700 bg-black/70 text-yellow-500 hover:border-yellow-500 hover:text-yellow-300 focus:outline-none focus-visible:ring-1 focus-visible:ring-yellow-500 transition duration-300 ease-in-out">
            <i class="fa-duotone fa-solid fa-xmark fa-lg"></i>
        </button>

        {{-- @click.self on both layers: clicking the dark surround OR the bare
             map between markers closes. Markers are child <a>s, so .self never
             fires for them and navigation is untouched. The map is a background
             image rather than an <img> precisely so nothing sits over the box
             to swallow those clicks. --}}
        {{-- Sized by HEIGHT, panned horizontally. The map fills the space it is
             given and takes whatever width its 1301:733 ratio then demands; when
             that is wider than the viewport — every phone held upright — this
             container scrolls instead of letterboxing the map into a strip.
             Fitting to width was the alternative and it wasted ~75% of a
             375x812 screen on black.

             Centring is `m-auto` on the child, NOT justify-center here: a
             centred flex item that overflows puts its left edge out of reach of
             the scroller. Auto margins collapse to 0 once it overflows, so the
             whole map stays scrollable. shrink-0 stops flex from squeezing it
             back to fit, which would break the ratio and drag every marker off
             its building.

             pt-20 reserves the close button's corner so it never lands on the
             map — the Hospital plaque sits high and right, under it otherwise. --}}
        <div x-ref="mapScroll" class="relative flex flex-1 overflow-x-auto overflow-y-hidden p-4 pt-20" @click.self="map = false">
            <div class="relative m-auto aspect-[1301/733] h-full w-auto shrink-0 border border-yellow-700 bg-cover bg-center shadow-2xl shadow-black"
                 @click.self="map = false"
                 style="background-image: url('{{ asset('images/map.png') }}');">

                @foreach ($locations as $location)
                    @php $active = request()->routeIs($location['route']); @endphp

                    <a href="{{ route($location['route']) }}" wire:navigate
                       @class([
                           'group absolute -translate-x-1/2 -translate-y-1/2 focus:outline-none',
                           'text-shadow-lg shadow-yellow-600' => $active,
                       ])
                       style="left: {{ $location['x'] }}%; top: {{ $location['y'] }}%;"
                       @if ($active) aria-current="page" @endif>

                        {{-- One size at every breakpoint. The plaques used to
                             shrink below sm: back when the map was fitted to
                             width and went tiny on a phone; now the map is sized
                             by height and is never small, so width-based
                             shrinking only produced specks on a large map. --}}
                        <x-dark-leather @class([
                            'relative flex items-center gap-x-2 border px-3 py-2 transition duration-300 ease-in-out',
                            'border-yellow-500 text-yellow-500 shadow-lg shadow-yellow-600/50' => $active,
                            'border-yellow-700 text-white shadow-lg shadow-black/70 group-hover:border-yellow-500 group-hover:text-yellow-500 group-focus-visible:border-yellow-500 group-focus-visible:text-yellow-500' => ! $active,
                        ])>
                            <x-brass-corners class="h-4 w-4 text-yellow-600/70" />

                            <i class="fa-duotone fa-solid {{ $location['icon'] }} relative text-base {{ $active ? 'text-yellow-500' : 'text-yellow-600' }}"></i>
                            <span class="relative whitespace-nowrap font-newRocker text-sm leading-none">
                                {{ $location['label'] }}
                            </span>
                        </x-dark-leather>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</nav>
