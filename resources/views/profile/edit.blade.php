<x-app-layout>
    <x-slot name="header">
        <x-label class="font-uncialAntiqua text-4xl sm:text-6xl text-yellow-500 text-shadow-lg shadow-yellow-600">
            {{ __('Profile') }}
        </x-label>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <x-dark-leather class="p-4 sm:p-8 border border-yellow-700">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </x-dark-leather>

            <x-dark-leather class="p-4 sm:p-8 border border-yellow-700">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </x-dark-leather>

            <x-dark-leather class="p-4 sm:p-8 border border-yellow-700">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </x-dark-leather>
        </div>
    </div>
</x-app-layout>
