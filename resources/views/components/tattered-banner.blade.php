{{--
    Torn ribbon used as a title/header backing instead of a plain rectangle.
    Swallowtail notches at both ends; two hairlines inside for weave, nothing
    denser — the slot content has to stay the loudest thing here.
--}}
<div {{ $attributes->merge(['class' => 'relative text-yellow-600/70']) }}>
    <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 300 60"
         preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path d="M0 5 L300 5 L286 30 L300 55 L0 55 L14 30 Z"
              fill="#2a1a10" fill-opacity=".55"
              stroke="currentColor" stroke-width="1" vector-effect="non-scaling-stroke"/>
        <g stroke="currentColor" stroke-width="1" stroke-opacity=".28" vector-effect="non-scaling-stroke">
            <path d="M22 13 L278 13"/>
            <path d="M22 47 L278 47"/>
        </g>
    </svg>

    <div class="relative px-3 py-3">{{ $slot }}</div>
</div>
