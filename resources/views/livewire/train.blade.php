<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Train') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('status'))
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg">
                    {{ session('status') }}
                </div>
            @endif

            @if (session('error'))
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Strength') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->strength }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Defense') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->defense }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Agility') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->agility }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Energy') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->energy }} / {{ $this->character->max_energy }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <h3 class="text-lg font-semibold">{{ __('Strength') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Current') }}: {{ $this->character->strength }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Costs') }} {{ \App\Services\TrainingService::ENERGY_COST }} {{ __('energy') }}
                        </p>
                        <x-primary-button
                            wire:click="train('strength')"
                            wire:loading.attr="disabled"
                            wire:target="train"
                        >
                            {{ __('Train Strength') }}
                        </x-primary-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <h3 class="text-lg font-semibold">{{ __('Defense') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Current') }}: {{ $this->character->defense }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Costs') }} {{ \App\Services\TrainingService::ENERGY_COST }} {{ __('energy') }}
                        </p>
                        <x-primary-button
                            wire:click="train('defense')"
                            wire:loading.attr="disabled"
                            wire:target="train"
                        >
                            {{ __('Train Defense') }}
                        </x-primary-button>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <h3 class="text-lg font-semibold">{{ __('Agility') }}</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Current') }}: {{ $this->character->agility }}
                        </p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('Costs') }} {{ \App\Services\TrainingService::ENERGY_COST }} {{ __('energy') }}
                        </p>
                        <x-primary-button
                            wire:click="train('agility')"
                            wire:loading.attr="disabled"
                            wire:target="train"
                        >
                            {{ __('Train Agility') }}
                        </x-primary-button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
