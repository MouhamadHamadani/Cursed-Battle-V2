@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'bg-black/60 border-yellow-700 text-white placeholder-stone-500 focus:border-yellow-500 focus:ring-yellow-600 shadow-sm']) }}>
