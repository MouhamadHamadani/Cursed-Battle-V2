@props(['small' => false])

{{--
    Section rule: a hairline gold run that fades at both ends with a single gem
    stud at its centre. Neutral furniture — it separates, it does not comment.

    Deliberately NOT <x-chain-divider>, which is the closer-looking component.
    The chain already carries an assigned meaning: work.blade.php, train.blade.php
    and hospital.blade.php each comment that it marks a *blocked* panel and goes
    nowhere else ("Chain reads as 'bound'"). Reusing it here would say "bound"
    over sections where nothing is bound.
--}}
<div {{ $attributes->merge(['class' => 'relative h-px w-full']) }} role="separator">
    <div class="absolute inset-0 bg-gradient-to-r from-transparent via-yellow-700 to-transparent"></div>

    {{-- Rotated square rather than a diamond glyph: no icon dependency, and it
         keeps its hairline ring crisp at both sizes. --}}
    <div @class([
        'absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rotate-45 bg-yellow-500 ring-1 ring-yellow-600 shadow-lg shadow-yellow-600/50',
        'h-2.5 w-2.5' => ! $small,
        'h-1.5 w-1.5' => $small,
    ])></div>
</div>
