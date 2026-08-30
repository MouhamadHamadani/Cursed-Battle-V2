@props(['maxWidth' => '2xl', 'show' => null])

@php
$maxWidth = [
    'sm' => 'sm:max-w-sm',
    'md' => 'sm:max-w-md',
    'lg' => 'sm:max-w-lg',
    'xl' => 'sm:max-w-xl',
    '2xl' => 'sm:max-w-2xl',
    '5xl' => 'sm:max-w-5xl',
    '7xl' => 'sm:max-w-7xl',
][$maxWidth];
@endphp

{{--
    Open state comes from a Livewire property via wire:model (V1's usage), or
    from a plain Alpine expression via `show` when the component has no
    property to bind — see the battle result modal.
--}}
<div
    @if ($show !== null)
        x-data="{ show: {{ $show }} }"
    @else
        x-data="{ show: @entangle($attributes->wire('model')).live }"
    @endif
    x-on:close.stop="show = false"
    x-on:keydown.escape.window="show = false"
    x-show="show"
    class="fixed inset-0 overflow-y-auto px-4 py-6 sm:px-0 z-50"
    style="display: none;"
>
    <div x-show="show" class="fixed inset-0 transform transition-all" x-on:click="show = false"
         x-transition:enter="ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0">
        <div class="absolute inset-0 bg-black opacity-80"></div>
    </div>

    <x-dark-leather x-show="show"
                    class="mb-6 border border-yellow-700 overflow-hidden shadow-xl transform transition-all sm:w-full {{ $maxWidth }} sm:mx-auto"
                    x-trap.inert.noscroll="show"
                    x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95">
        {{ $slot }}
    </x-dark-leather>
</div>
