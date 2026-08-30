@php
    // View-only read of the authed character for the V1-style stat strip.
    $navCharacter = Auth::user()?->character;
@endphp

<nav x-data="{ open: false }">
    <x-dark-leather class="border-b border-yellow-700">
        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center">
                    <!-- Logo -->
                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('dashboard') }}" title="{{ config('app.name', 'Cursed Battle') }}">
                            <x-application-logo class="block h-12 w-auto" />
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 sm:ms-10 sm:flex">
                        <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                            {{ __('Dashboard') }}
                        </x-nav-link>
                        <x-nav-link :href="route('work')" :active="request()->routeIs('work')">
                            {{ __('Work') }}
                        </x-nav-link>
                        <x-nav-link :href="route('train')" :active="request()->routeIs('train')">
                            {{ __('Train') }}
                        </x-nav-link>
                        <x-nav-link :href="route('market')" :active="request()->routeIs('market')">
                            {{ __('Market') }}
                        </x-nav-link>
                        <x-nav-link :href="route('battle')" :active="request()->routeIs('battle')">
                            {{ __('Battle') }}
                        </x-nav-link>
                        <x-nav-link :href="route('hospital')" :active="request()->routeIs('hospital')">
                            {{ __('Hospital') }}
                        </x-nav-link>
                    </div>
                </div>

                <!-- Settings Dropdown -->
                <div class="hidden sm:flex sm:items-center sm:ms-6">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent font-newRocker text-white hover:text-yellow-500 focus:outline-none transition ease-in-out duration-300">
                                <div>{{ Auth::user()->name }}</div>
                                <i class="fa-duotone fa-solid fa-chevron-down ms-2 text-yellow-500"></i>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
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

                <!-- Hamburger -->
                <div class="-me-2 flex items-center sm:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-yellow-700 hover:text-yellow-500 focus:outline-none transition duration-300 ease-in-out">
                        <i :class="{'hidden': open }" class="fa-duotone fa-solid fa-bars fa-2x"></i>
                        <i :class="{'hidden': ! open }" class="hidden fa-duotone fa-solid fa-xmark fa-2x"></i>
                    </button>
                </div>
            </div>

            <!-- Character stat strip (V1's nav bar) -->
            @if ($navCharacter)
                <x-dark-wall class="flex flex-wrap items-center justify-center gap-5 mb-3 p-3 border border-yellow-700">
                    <div class="flex flex-1 justify-center gap-x-2 items-center" title="{{ __('XP') }}">
                        <i class="fa-duotone fa-solid fa-star text-yellow-500"></i>
                        <x-label>{{ $navCharacter->xp }}</x-label>
                    </div>
                    <div class="flex flex-1 justify-center gap-x-2 items-center" title="{{ __('Level') }}">
                        <i class="fa-duotone fa-solid fa-shield-halved text-yellow-500"></i>
                        <x-label>{{ $navCharacter->level }}</x-label>
                    </div>
                    <div class="flex flex-1 justify-center gap-x-2 items-center" title="{{ __('Gold') }}">
                        <i class="fa-duotone fa-solid fa-coins text-yellow-500"></i>
                        <x-label>{{ $navCharacter->gold }}</x-label>
                    </div>
                    <div class="flex flex-1 justify-center gap-x-2 items-center" title="{{ __('Health') }}">
                        <i class="fa-duotone fa-solid fa-heart text-red-500"></i>
                        <x-label>{{ $navCharacter->health }} / {{ $navCharacter->max_health }}</x-label>
                    </div>
                    <div class="flex flex-1 justify-center gap-x-2 items-center" title="{{ __('Energy') }}">
                        <i class="fa-duotone fa-solid fa-bolt text-yellow-700"></i>
                        <x-label>{{ $navCharacter->energy }} / {{ $navCharacter->max_energy }}</x-label>
                    </div>
                </x-dark-wall>
            @endif
        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('work')" :active="request()->routeIs('work')">
                    {{ __('Work') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('train')" :active="request()->routeIs('train')">
                    {{ __('Train') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('market')" :active="request()->routeIs('market')">
                    {{ __('Market') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('battle')" :active="request()->routeIs('battle')">
                    {{ __('Battle') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('hospital')" :active="request()->routeIs('hospital')">
                    {{ __('Hospital') }}
                </x-responsive-nav-link>
            </div>

            <!-- Responsive Settings Options -->
            <div class="pt-4 pb-1 border-t border-yellow-700">
                <div class="px-4">
                    <div class="font-newRocker text-yellow-500">{{ Auth::user()->name }}</div>
                    <div class="font-sans text-sm text-stone-400">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <!-- Authentication -->
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        </div>
    </x-dark-leather>
</nav>
