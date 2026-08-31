{{--
    Levantine accent: a keel/horseshoe arch outline drawn around hero content.
    Stroke only — it frames, it does not fill or tint what it wraps.
    viewBox is deliberately ~3:1 to match the wide, shallow box it frames, so
    preserveAspectRatio="none" barely distorts the curve.
--}}
<div {{ $attributes->merge(['class' => 'relative text-brass/70']) }}>
    <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 300 100"
         preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <path d="M14 100 L14 64 C14 50 8 40 34 28 C80 26 130 22 150 4
                 C170 22 220 26 266 28 C292 40 286 50 286 64 L286 100"
              fill="none" stroke="currentColor" stroke-width="1.5" vector-effect="non-scaling-stroke"/>
    </svg>

    <div class="relative px-10 pt-10 pb-4">
        {{ $slot }}
    </div>
</div>
