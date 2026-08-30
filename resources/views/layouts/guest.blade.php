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
        <x-dark-wall class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-black">
            <div>
                <a href="/">
                    <x-application-logo class="w-40 h-40" />
                </a>
            </div>

            <x-dark-leather class="w-full sm:max-w-md mt-6 px-6 py-6 border border-yellow-700 shadow-md overflow-hidden">
                {{ $slot }}
            </x-dark-leather>
        </x-dark-wall>

        @livewireScripts
    </body>
</html>
