<x-app-layout>
    <x-slot name="header">
        {{-- Bare title, same as Work, Train, Battle, Market and Hospital. The
             torn-ribbon backing is gone; <x-tattered-banner> itself stays for
             the faction-select page, which still uses it. --}}
        <x-label as="h1" class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Home') }}
        </x-label>
    </x-slot>

    {{-- Everything below the header lives in the component, matching Work,
         Train, Battle, Market and Hospital. The quick-link cards in particular
         have to be inside it: they read the character's busy/hospitalized state
         to show which of the four are currently closed, and only a Livewire
         component re-renders on the 'character-updated' event, so a session
         finishing unlocks them in place instead of on the next page load. --}}
    <livewire:home />
</x-app-layout>
