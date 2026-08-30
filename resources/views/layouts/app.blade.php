<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/favicon-16x16.png') }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Icons (Font Awesome Pro 6, self-hosted from public/webfonts) -->
        <link rel="stylesheet" href="{{ asset('css/all.css') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        {{-- livewire 3.x/installation: manual asset directives, Alpine bundled --}}
        @livewireStyles
    </head>
    <body class="font-newRocker antialiased text-white">
        <x-dark-wall class="min-h-screen bg-black">
            @include('layouts.navigation')

            <!-- Page Heading -->
            @isset($header)
                <x-dark-leather class="border-y border-yellow-700">
                    <div class="max-w-7xl mx-auto py-10 px-4 sm:px-6 lg:px-8 text-center">
                        {{ $header }}
                    </div>
                </x-dark-leather>

                <x-divider />
            @endisset

            <!-- Page Content -->
            <main>
                {{ $slot }}
            </main>

            <x-divider />

            <x-dark-leather class="border-t border-yellow-700 px-5 py-10 text-center">
                <x-application-logo class="block h-24 mx-auto" />
                <x-label class="text-2xl text-red-500 mt-3">{{ config('app.name', 'Cursed Battle') }}</x-label>
            </x-dark-leather>
        </x-dark-wall>

        @stack('modals')

        @livewireScripts
        @stack('scripts')
    </body>
</html>
