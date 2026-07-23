<div>
    @if ($character)
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-6">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Level') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->level }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('XP') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->xp }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Gold') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->gold }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Health') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->health }} / {{ $character->max_health }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Energy') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->energy }} / {{ $character->max_energy }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Strength') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->strength }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Defense') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->defense }}</p>
            </div>
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Agility') }}</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $character->agility }}</p>
            </div>
        </div>
    @else
        <p class="text-gray-500 dark:text-gray-400">{{ __('No character found.') }}</p>
    @endif
</div>
