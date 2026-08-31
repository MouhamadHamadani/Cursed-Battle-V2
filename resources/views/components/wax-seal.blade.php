@props(['size' => 56])

{{--
    Wax-seal stamp: blood-red wax disc, gold sigil ring, crossed blades to echo
    the logo. A focal badge — keep it small (40–70px), never a background.
--}}
<svg {{ $attributes->merge(['class' => 'shrink-0']) }}
     width="{{ $size }}" height="{{ $size }}" viewBox="0 0 64 64"
     role="img" aria-label="{{ __('Seal') }}">
    <circle cx="32" cy="32" r="30" fill="#3a0f0f"/>
    <circle cx="32" cy="32" r="29" fill="none" stroke="#8a1f1f" stroke-width="2.5"/>
    <circle cx="32" cy="32" r="22" fill="none" stroke="#c9a13b" stroke-width="1.2" stroke-opacity=".75"/>

    <g fill="none" stroke="#c9a13b" stroke-width="2.2" stroke-linecap="round">
        <path d="M19 45 L43 21"/>
        <path d="M45 45 L21 21"/>
        <path d="M21 38 L28 45" stroke-width="1.8"/>
        <path d="M43 38 L36 45" stroke-width="1.8"/>
    </g>
    <g fill="#c9a13b">
        <circle cx="18" cy="46" r="2"/>
        <circle cx="46" cy="46" r="2"/>
    </g>
</svg>
