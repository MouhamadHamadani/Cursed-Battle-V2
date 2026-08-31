{{-- Levantine accent divider: brass rule tapering into a centred diamond. --}}
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 text-brass']) }} role="separator">
    <span class="h-px flex-1 bg-gradient-to-r from-transparent to-current opacity-70"></span>

    <svg class="shrink-0" width="14" height="14" viewBox="0 0 14 14" aria-hidden="true" focusable="false">
        <path d="M7 .5 L13.5 7 L7 13.5 L.5 7 Z" fill="none" stroke="currentColor" stroke-width="1.2"/>
        <path d="M7 4 L10 7 L7 10 L4 7 Z" fill="currentColor"/>
    </svg>

    <span class="h-px flex-1 bg-gradient-to-l from-transparent to-current opacity-70"></span>
</div>
