{{-- V1 theme panel, lighter texture — secondary sections, item/opponent cards. --}}
<div {{ $attributes->merge(['class' => '']) }} style="background-image: url('{{ asset('images/dark_wall.png') }}');">
    {{ $slot }}
</div>
