<x-app-layout>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Dashboard') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">
            <x-dark-leather class="border border-yellow-700 p-6">
                <livewire:dashboard />
            </x-dark-leather>

            {{-- Quick links to the four places a character can spend a turn. --}}
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach ([
                    ['route' => 'work', 'label' => __('Work'), 'icon' => 'fa-hammer', 'blurb' => __('Trade energy for gold.')],
                    ['route' => 'train', 'label' => __('Train'), 'icon' => 'fa-dumbbell', 'blurb' => __('Sharpen thy stats.')],
                    ['route' => 'market', 'label' => __('Market'), 'icon' => 'fa-treasure-chest', 'blurb' => __('Arm and armour thyself.')],
                    ['route' => 'battle', 'label' => __('Battle'), 'icon' => 'fa-swords', 'blurb' => __('Test thy steel on another.')],
                ] as $link)
                    <a href="{{ route($link['route']) }}">
                        <x-dark-wall class="h-full border border-yellow-700 p-6 text-center hover:border-yellow-500 transition duration-300">
                            <i class="fa-duotone fa-solid {{ $link['icon'] }} fa-3x text-yellow-500"></i>
                            <x-label class="text-2xl mt-3">{{ $link['label'] }}</x-label>
                            <p class="mt-1 font-sans text-xs text-stone-400">{{ $link['blurb'] }}</p>
                        </x-dark-wall>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-app-layout>
