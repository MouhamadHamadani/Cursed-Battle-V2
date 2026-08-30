@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-newRocker text-sm text-green-500']) }}>
        {{ $status }}
    </div>
@endif
