<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Work') }}
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
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Energy') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->energy }} / {{ $this->character->max_energy }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gold') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->gold }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                @foreach ($this->occupations as $occupation)
                    @php
                        $qualifies = $this->character->level >= $occupation->min_level
                            && ($occupation->max_level === null || $this->character->level <= $occupation->max_level);
                    @endphp

                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                            <h3 class="text-lg font-semibold">{{ $occupation->name }}</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $occupation->description }}</p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ __('Level') }} {{ $occupation->min_level }}&ndash;{{ $occupation->max_level ?? '∞' }}
                            </p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                {{ $occupation->gold_per_energy }} {{ __('gold/energy') }}
                            </p>

                            @if ($qualifies)
                                <x-primary-button
                                    wire:click="work({{ $occupation->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="work"
                                >
                                    {{ __('Work a shift') }}
                                </x-primary-button>
                            @else
                                <span class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-500 dark:text-gray-400 uppercase tracking-widest cursor-not-allowed">
                                    {{ __('Locked') }}
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

        </div>
    </div>
</div>
