{{--
    Chain-link divider: alternating oval links tiled as an SVG pattern, masked
    so the run fades out at both ends instead of butting into the panel edge.
    Ids are uniqued because a pattern/mask id collision would silently blank a
    second instance on the same page.

    Link geometry is scaled up ~20% over the first pass and the stroke is
    heavier than proportional — at divider size the thin version read as a row
    of faint circles rather than as links. Still a hairline accent, not a rule.
--}}
@php $uid = uniqid('chain'); @endphp

<div {{ $attributes->merge(['class' => 'mx-auto max-w-xs text-yellow-600/70']) }} role="separator">
    <svg class="block w-full" height="22" aria-hidden="true" focusable="false">
        <defs>
            <pattern id="{{ $uid }}-p" width="29" height="22" patternUnits="userSpaceOnUse">
                <g fill="none" stroke="currentColor" stroke-width="1.8">
                    <ellipse cx="8.5" cy="11" rx="6.7" ry="9.8"/>
                    <ellipse cx="20.7" cy="11" rx="7.9" ry="5.5"/>
                </g>
            </pattern>
            <linearGradient id="{{ $uid }}-g" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="black"/>
                <stop offset=".22" stop-color="white"/>
                <stop offset=".78" stop-color="white"/>
                <stop offset="1" stop-color="black"/>
            </linearGradient>
            <mask id="{{ $uid }}-m">
                <rect width="100%" height="22" fill="url(#{{ $uid }}-g)"/>
            </mask>
        </defs>
        <rect width="100%" height="22" fill="url(#{{ $uid }}-p)" mask="url(#{{ $uid }}-m)"/>
    </svg>
</div>
