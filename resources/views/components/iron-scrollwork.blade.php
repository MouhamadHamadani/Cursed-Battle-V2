{{--
    Wrought-iron corner flourishes. Four fixed-size SVGs pinned to the corners
    of a relative box — deliberately NOT a tiled fill, so the panel interior
    stays plain behind the text.

    The corners render AFTER the slot on purpose: the panel they wrap is
    usually opaque (x-dark-leather), and anything painted before it disappears
    underneath. They need to sit on top, overlapping the panel edge.
--}}
@php
    $corners = [
        'top-0 left-0'     => '',
        'top-0 right-0'    => 'scale-x-[-1]',
        'bottom-0 left-0'  => 'scale-y-[-1]',
        'bottom-0 right-0' => 'scale-x-[-1] scale-y-[-1]',
    ];
@endphp

<div {{ $attributes->merge(['class' => 'relative text-yellow-600/70']) }}>
    <div class="relative">{{ $slot }}</div>

    @foreach ($corners as $position => $flip)
        <svg class="pointer-events-none absolute {{ $position }} {{ $flip }}"
             width="40" height="40" viewBox="0 0 48 48" aria-hidden="true" focusable="false">
            <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round">
                <path d="M0 46 L0 18 C0 8 8 0 18 0 L46 0"/>
                <path d="M0 30 C10 30 18 24 20 14 C21 8 27 8 28 13 C29 18 24 20 21 17"/>
            </g>
        </svg>
    @endforeach
</div>
