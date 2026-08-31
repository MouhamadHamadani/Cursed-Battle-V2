<div class="py-12">
    <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
        <div class="text-center mb-10">
            {{-- Banner, not an arch: this renders in layouts.app, so it follows
                 the Home page-title treatment rather than the auth-card one. --}}
            <x-tattered-banner class="mx-auto max-w-2xl">
                <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
                    {{ __('Pick Your Faction') }}
                </x-label>
            </x-tattered-banner>
            <p class="mt-3 font-sans text-sm text-stone-300">
                {{ __('Look upon both banners as long as thou wilt. Nothing is sworn until thou sayest so — and once sworn, never unsworn.') }}
            </p>
        </div>

        {{-- Headcount is deliberately absent here: it belongs to Home only. --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            @foreach (\App\Models\Character::FACTIONS as $faction)
                <button type="button" wire:click="previewFaction('{{ $faction }}')" class="text-left">
                    <x-dark-wall class="h-full border border-yellow-700 p-8 text-center hover:border-yellow-500 transition duration-300">
                        <i class="fa-duotone fa-solid fa-flag-swallowtail fa-3x text-yellow-500"></i>
                        <x-label class="block text-3xl mt-4 text-yellow-500">
                            {{ \App\Models\Character::factionLabel($faction) }}
                        </x-label>
                        <p class="mt-2 font-sans text-xs text-stone-400">{{ __('Read the banner') }}</p>
                    </x-dark-wall>
                </button>
            @endforeach
        </div>
    </div>

    @if ($preview)
        <x-dark-modal wire:model.live="showPreview" maxWidth="lg">
            <div class="p-8 text-center">
                <x-label class="font-uncialAntiqua text-3xl text-yellow-500">
                    {{ \App\Models\Character::factionLabel($preview) }}
                </x-label>

                <x-chain-divider class="my-5" />

                <p class="font-sans text-sm text-stone-300">
                    {{ \App\Models\Character::factionDescription($preview) }}
                </p>

                <div class="flex flex-wrap justify-center gap-4 mt-8">
                    <x-primary-button type="button" target="confirm" wire:click="confirm">
                        {{ __('Confirm joining :faction', ['faction' => \App\Models\Character::factionLabel($preview)]) }}
                    </x-primary-button>

                    <x-secondary-button x-on:click="show = false">
                        {{ __('Look elsewhere') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-dark-modal>
    @endif
</div>
