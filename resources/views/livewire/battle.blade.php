<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Battle') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('error'))
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Name') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->user->name }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Level') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->level }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Health') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->health }} / {{ $this->character->max_health }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gold') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->gold }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ __('Opponents') }}</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    @forelse ($this->opponents as $opponent)
                        <div wire:key="opponent-{{ $opponent->id }}" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                                <h4 class="text-lg font-semibold">{{ $opponent->user->name ?? 'Unknown' }}</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Level') }} {{ $opponent->level }}</p>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Health') }} {{ $opponent->health }} / {{ $opponent->max_health }}</p>
                                <x-primary-button
                                    wire:click="attack({{ $opponent->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="attack"
                                >
                                    {{ __('Attack') }}
                                </x-primary-button>
                            </div>
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('No opponents available.') }}</p>
                    @endforelse
                </div>
            </div>

            @if ($lastFight)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-4">
                        <div class="flex items-center gap-3">
                            <h3 class="text-lg font-semibold">{{ __('Result') }}</h3>
                            @if ($lastFight['knockout'])
                                <span class="inline-flex items-center px-2 py-1 bg-red-100 dark:bg-red-900 text-red-700 dark:text-red-200 rounded text-xs font-semibold uppercase tracking-widest">
                                    {{ __('Knockout') }}
                                </span>
                            @endif
                            @if ($lastFight['leveled_up'])
                                <span class="inline-flex items-center px-2 py-1 bg-green-100 dark:bg-green-900 text-green-700 dark:text-green-200 rounded text-xs font-semibold uppercase tracking-widest">
                                    {{ __('Leveled up!') }}
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-6">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Winner') }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lastFight['winner_name'] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Rounds') }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lastFight['rounds'] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gold') }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lastFight['gold_change'] >= 0 ? '+' : '' }}{{ $lastFight['gold_change'] }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('XP') }}</p>
                                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $lastFight['xp_change'] >= 0 ? '+' : '' }}{{ $lastFight['xp_change'] }}</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            @foreach ($lastFight['events'] as $i => $event)
                                <p wire:key="ev-{{ $i }}" class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Round') }} {{ $event['round'] }}:
                                    {{ $event['actor'] === 'attacker' ? $lastFight['attacker_name'] : $lastFight['defender_name'] }}
                                    {{ $event['dodged'] ? __('missed (dodged)') : __('hit for :damage', ['damage' => $event['damage']]) }}
                                    ({{ __('target HP') }} {{ $event['target_hp'] }})
                                </p>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

        </div>
    </div>
</div>
