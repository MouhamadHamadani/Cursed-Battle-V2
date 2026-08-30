@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-yellow-500 font-newRocker leading-5 text-yellow-500 text-shadow-lg shadow-yellow-600 focus:outline-none transition duration-300 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent font-newRocker leading-5 text-white hover:text-yellow-500 hover:border-yellow-700 focus:outline-none transition duration-300 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
