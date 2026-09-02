@props(['value', 'as' => 'label'])

{{--
    V1's text primitive. Deliberately carries no size or colour so callers can
    set both without `!important` overrides — only the display face is fixed.

    `as` swaps the rendered tag and nothing else. The app had no <h1>–<h4>
    anywhere, so no page had a document outline; a page title can now ask for
    `as="h1"` without leaving the one text primitive behind or restating its
    classes. The default is unchanged, so every existing caller still renders
    the historic <label>.
--}}
<{{ $as }} {{ $attributes->merge(['class' => 'block font-newRocker']) }}>
    {{ $value ?? $slot }}
</{{ $as }}>
