@props(['value'])

{{--
    V1's text primitive. Deliberately carries no size or colour so callers can
    set both without `!important` overrides — only the display face is fixed.
--}}
<label {{ $attributes->merge(['class' => 'block font-newRocker']) }}>
    {{ $value ?? $slot }}
</label>
