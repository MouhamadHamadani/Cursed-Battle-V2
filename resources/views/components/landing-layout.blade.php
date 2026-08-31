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
            Separate from <x-guest-layout> on purpose: that one centres a
            max-w-md leather card, which is right for a login form and wrong
            for a full-bleed marketing page. Same <head>, no card.

            Anonymous component rather than layouts/ + an App\View\Components
            class like Breeze's two — it needs no constructor, so the class
            would be four lines of nothing.

            @livewireScripts still ships the wire:navigate handler even though
            nothing on this page is a Livewire component, so the links here
            join the same SPA navigation as the rest of the app.
        --}}
        <x-dark-wall class="min-h-screen bg-black">
            {{ $slot }}

            <x-divider />

            <x-dark-leather class="border-t border-yellow-700 px-5 py-10 text-center">
                <x-application-logo class="block h-24 mx-auto" />
                <x-label class="text-2xl text-red-500 mt-3">{{ config('app.name', 'Cursed Battle') }}</x-label>
            </x-dark-leather>
        </x-dark-wall>

        @livewireScripts
    </body>
</html>
