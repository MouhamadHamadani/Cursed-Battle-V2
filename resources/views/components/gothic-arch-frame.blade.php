{{--
    Gothic pointed-arch frame for hero blocks. Double stroke (outer + inner,
    concentric so the offset stays even around the head).

    Two-centred drop arch: each arc is centred past the apex on the opposite
    side, which is what makes the head meet in a point instead of a dome. That
    only works when the head is TALLER than half the span — so this component
    needs a narrow, tall box. The viewBox aspect (~1.57) is matched by the
    default padding below; widen the caller and you must add height with it or
    the point flattens back into a rounded arch.
--}}
<div {{ $attributes->merge(['class' => 'relative mx-auto max-w-[400px] px-8 pt-20 pb-6 text-yellow-600/60']) }}>
    <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 300 191"
         preserveAspectRatio="none" aria-hidden="true" focusable="false">
        <g fill="none" stroke="currentColor" vector-effect="non-scaling-stroke" stroke-linecap="round">
            <path d="M20 191 L20 165 A160 160 0 0 1 150 8 A160 160 0 0 1 280 165 L280 191" stroke-width="1.6"/>
            <path d="M28 191 L28 165 A152 152 0 0 1 150 16 A152 152 0 0 1 272 165 L272 191"
                  stroke-width="1" stroke-opacity=".55"/>
        </g>
    </svg>

    <div class="relative">{{ $slot }}</div>
</div>
