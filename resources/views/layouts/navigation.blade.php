{{--
    V1 puts a live XP/level/gold/health/energy strip in this bar. Not ported:
    the layout renders outside every Livewire root, so the numbers would go
    stale the moment a player worked, trained or fought, and keeping them in
    sync needs its own Livewire component. Each page shows its own figures.
--}}
<nav x-data="{ open: false }">
    <x-dark-leather class="border-b border-yellow-700">
        <!-- Primary Navigation Menu -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-3">
                <div class="flex items-center">
                    <!-- Logo -->
                    <div class="shrink-0 flex items-center">
                        <a href="{{ route('home') }}" wire:navigate title="{{ config('app.name', 'Cursed Battle') }}">
                            @persist('logo')
                                <x-application-logo class="block h-12 w-auto" />
                            @endpersist
                        </a>
                    </div>

                    <!-- Navigation Links -->
                    <div class="hidden space-x-8 sm:ms-10 sm:flex">
                        <x-nav-link :href="route('home')" :active="request()->routeIs('home')" wire:navigate>
                            {{ __('Home') }}
                        </x-nav-link>
                        <x-nav-link :href="route('work')" :active="request()->routeIs('work')" wire:navigate>
                            {{ __('Work') }}
                        </x-nav-link>
                        <x-nav-link :href="route('train')" :active="request()->routeIs('train')" wire:navigate>
                            {{ __('Train') }}
                        </x-nav-link>
                        <x-nav-link :href="route('market')" :active="request()->routeIs('market')" wire:navigate>
                            {{ __('Market') }}
                        </x-nav-link>
                        <x-nav-link :href="route('battle')" :active="request()->routeIs('battle')" wire:navigate>
                            {{ __('Battle') }}
                        </x-nav-link>
                        <x-nav-link :href="route('hospital')" :active="request()->routeIs('hospital')" wire:navigate>
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

                <!-- Hamburger -->
                <div class="-me-2 flex items-center sm:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 text-yellow-700 hover:text-yellow-500 focus:outline-none transition duration-300 ease-in-out">
                        <i :class="{'hidden': open }" class="fa-duotone fa-solid fa-bars fa-2x"></i>
                        <i :class="{'hidden': ! open }" class="hidden fa-duotone fa-solid fa-xmark fa-2x"></i>
                    </button>
                </div>
            </div>

        </div>

        <!-- Responsive Navigation Menu -->
        <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
            <div class="pt-2 pb-3 space-y-1">
                <x-responsive-nav-link :href="route('home')" :active="request()->routeIs('home')" wire:navigate>
                    {{ __('Home') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('work')" :active="request()->routeIs('work')" wire:navigate>
                    {{ __('Work') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('train')" :active="request()->routeIs('train')" wire:navigate>
                    {{ __('Train') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('market')" :active="request()->routeIs('market')" wire:navigate>
                    {{ __('Market') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('battle')" :active="request()->routeIs('battle')" wire:navigate>
                    {{ __('Battle') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('hospital')" :active="request()->routeIs('hospital')" wire:navigate>
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
                    <x-responsive-nav-link :href="route('profile.edit')" wire:navigate>
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
