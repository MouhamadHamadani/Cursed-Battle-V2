{{--
    Brass corner brackets: one shape rotated into each corner, double stroke to
    match <x-gothic-arch-frame>. Inset off the border so the two lines don't
    collide. Extracted from the auth card so the map plaques get the same
    ornament without six more copies of the path data.

    Absolutely positioned — the caller must be `relative`, and must stack its
    own content over these (they are pointer-events-none, but a plain sibling
    would still sit under them in paint order).

    Size/colour override by passing a class, e.g. `class="h-4 w-4"`. It has to
    be a literal string in the CALLING file: Tailwind only generates classes it
    can see in the source, so a value assembled from a prop never gets built.
--}}
@foreach ([
    'top-1.5 start-1.5' => '',
    'top-1.5 end-1.5' => 'rotate-90',
    'bottom-1.5 end-1.5' => 'rotate-180',
    'bottom-1.5 start-1.5' => '-rotate-90',
] as $corner => $spin)
    <svg class="pointer-events-none absolute {{ $corner }} {{ $spin }} {{ $attributes->get('class', 'h-7 w-7 text-yellow-600/70') }}"
         viewBox="0 0 28 28" fill="none" stroke="currentColor"
         stroke-linecap="round" aria-hidden="true" focusable="false">
        <path d="M26 1 H6 A5 5 0 0 0 1 6 V26" stroke-width="1.6"/>
        <path d="M26 5.5 H8 A2.5 2.5 0 0 0 5.5 8 V26" stroke-width="1" stroke-opacity=".55"/>
    </svg>
@endforeach
