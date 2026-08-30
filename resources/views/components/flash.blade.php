{{--
    Session flash banner in V1's idiom — a bordered black panel with a gold or
    red label. Renders nothing when neither key is set.
--}}
@if (session('status'))
    <x-dark-wall class="border border-green-500 p-4 text-center">
        <x-label class="text-green-500">
            <i class="fa-duotone fa-solid fa-circle-check me-2"></i>{{ session('status') }}
        </x-label>
    </x-dark-wall>
@endif

@if (session('error'))
    <x-dark-wall class="border border-red-900 p-4 text-center">
        <x-label class="text-red-500">
            <i class="fa-duotone fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}
        </x-label>
    </x-dark-wall>
@endif
