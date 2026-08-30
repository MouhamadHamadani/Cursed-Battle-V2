{{-- V1 theme panel: tiled leather texture, no opinions of its own. --}}
<div {{ $attributes->merge(['class' => '']) }} style="background-image: url('{{ asset('images/dark_leather.png') }}');">
    {{ $slot }}
</div>
