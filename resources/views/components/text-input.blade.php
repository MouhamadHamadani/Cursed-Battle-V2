@props(['disabled' => false, 'icon' => null])

{{--
    `icon` takes a Font Awesome face (e.g. "fa-envelope") and pads the field to
    clear it. Without one the wrapper is display:contents, so the callers that
    predate this prop — the profile partials included — lay out exactly as they
    did before rather than gaining a block-level box.
--}}
<div @class(['relative' => $icon, 'contents' => ! $icon])>
    @if ($icon)
        <i class="fa-duotone fa-solid {{ $icon }} pointer-events-none absolute inset-y-0 start-0 flex items-center ps-3 text-yellow-600/80"></i>
    @endif

    <input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-black/60 border-yellow-700 text-white placeholder-stone-500 shadow-sm transition duration-200 focus:border-yellow-500 focus:ring-yellow-600 focus:shadow-lg focus:shadow-yellow-600/40 ' . ($icon ? 'ps-10' : '')]) }}>
</div>
