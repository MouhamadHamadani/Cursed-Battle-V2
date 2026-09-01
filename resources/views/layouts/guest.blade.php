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
        {{--
            Same map as the landing hero, so arriving at the auth funnel reads
            as the same place rather than a blank void. Three layers, bottom up:
            <x-dark-wall>'s stone tile, the map over it at partial opacity so
            the grain still shows through, then a vignette that pulls the edges
            down and keeps the card legible wherever the map happens to be
            bright. The wash is CSS only — no second image asset.
        --}}
        <x-dark-wall class="relative min-h-screen bg-black">
            <div class="absolute inset-0 bg-cover bg-center opacity-40"
                 style="background-image: url('{{ asset('images/map.png') }}');"></div>

            {{-- Radial, not one of Tailwind's linear gradients: the card is
                 centred, so the falloff should be centred on it too. --}}
            <div class="absolute inset-0"
                 style="background: radial-gradient(ellipse at 50% 42%, rgba(0,0,0,.62) 0%, rgba(0,0,0,.90) 52%, rgba(0,0,0,.99) 100%);"></div>

            <div class="relative flex min-h-screen flex-col items-center px-4 py-8">
                <a href="/" class="mt-auto" wire:navigate>
                    <x-application-logo class="h-32 w-32" />
                </a>

                <x-dark-leather class="relative mt-2 w-full overflow-hidden border border-yellow-700/80 px-6 py-7 shadow-2xl shadow-yellow-900/40 sm:max-w-md">
                    <x-brass-corners class="h-7 w-7 text-yellow-600/70" />

                    {{-- Stacked over the brackets so form controls stay clickable. --}}
                    <div class="relative">{{ $slot }}</div>
                </x-dark-leather>

                {{-- Echoes the landing hero's tagline verbatim — same promise,
                     same words, so the two pages read as one pitch. --}}
                <p class="mb-auto mt-6 text-center font-sans text-xs uppercase tracking-[.2em] text-stone-400/80">
                    {{ __('Two banners. One field. No quarter.') }}
                </p>
            </div>
        </x-dark-wall>

        @livewireScripts
    </body>
</html>
