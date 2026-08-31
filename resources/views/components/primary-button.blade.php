{{--
    Breeze's primary button, themed — delegates to the V1 <x-button>, which
    already carries the transition and the gradient flip. Only the gold bloom
    is added here, so plain <x-button> callers keep the flatter treatment.
--}}
<x-button {{ $attributes->merge(['class' => 'hover:scale-[1.02] hover:shadow-lg hover:shadow-yellow-600/40']) }}>{{ $slot }}</x-button>
