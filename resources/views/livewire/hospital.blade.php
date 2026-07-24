<div>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Hospital') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            @if ($this->character->isHospitalized())
                <div class="bg-red-100 dark:bg-red-900 border border-red-400 dark:border-red-700 text-red-700 dark:text-red-200 px-4 py-3 rounded-lg">
                    {{ __('You are hospitalized.') }}
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100 space-y-3">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ __('You will be released :time.', ['time' => $this->character->hospitalized_until->diffForHumans()]) }}
                        </p>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('Health') }}</p>
                            <p class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $this->character->health }} / {{ $this->character->max_health }}</p>
                        </div>
                    </div>
                </div>
            @else
                <div class="bg-green-100 dark:bg-green-900 border border-green-400 dark:border-green-700 text-green-700 dark:text-green-200 px-4 py-3 rounded-lg">
                    {{ __('You are healthy and ready to fight.') }}
                </div>

                <a href="{{ route('battle') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                    {{ __('Go to Battle') }}
                </a>
            @endif

        </div>
    </div>
</div>
