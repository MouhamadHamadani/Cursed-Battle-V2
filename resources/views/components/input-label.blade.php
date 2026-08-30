@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-newRocker text-yellow-500 tracking-wide']) }}>
    {{ $value ?? $slot }}
</label>
