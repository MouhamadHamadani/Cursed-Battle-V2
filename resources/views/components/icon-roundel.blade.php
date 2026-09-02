@props(['icon', 'muted' => false, 'size' => 'sm'])

{{--
    Circular badge behind a duotone glyph. Nine of these on Home — five stat
    cells and four quick links — which is what earns it a component rather than
    nine copies of the same six classes.

    `muted` is the demoted palette the quick-link cards already use when a
    character is busy or hospitalized, so a closed card's roundel dims with the
    rest of the card instead of staying lit above stone text.

    Everything is folded into ONE merge: a second `class` attribute alongside
    $attributes->merge() is silently dropped by the HTML parser, which loses
    whichever half the caller did not write.

    The glyph is aria-hidden — every roundel sits directly above its own text
    label, so announcing it would double up.
--}}
@php
    $box = $size === 'lg' ? 'h-16 w-16' : 'h-11 w-11';
    $glyph = $size === 'lg' ? 'fa-2x' : 'fa-lg';
    $tone = $muted ? 'border-stone-700 bg-black/30' : 'border-yellow-700 bg-black/40';
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full border $box $tone"]) }}>
    <i class="fa-duotone fa-solid {{ $icon }} {{ $glyph }} {{ $muted ? 'text-stone-600' : 'text-yellow-500' }}"
       aria-hidden="true"></i>
</span>
