@props(['target' => null, 'disable' => false])

{{--
    V1's primary action button. Pass `target` to get V1's spinner-swap while the
    matching Livewire action is in flight; omit it for plain links/submits.
--}}
<button {{ $attributes->merge([
    'type' => 'submit',
    'class' => 'inline-flex items-center justify-center px-4 py-2 border border-yellow-700 bg-gradient-to-b from-red-600 to-red-900 hover:bg-gradient-to-t font-newRocker uppercase tracking-widest text-white transition ease-in-out duration-300 disabled:cursor-not-allowed disabled:from-stone-700 disabled:to-stone-900 disabled:text-stone-400 disabled:border-stone-700',
]) }} @disabled($disable)>
    @if ($target)
        <span wire:target="{{ $target }}" wire:loading.block>
            <i class="fa-duotone fa-solid fa-spinner fa-spin-pulse text-yellow-500"></i>
        </span>
        <span wire:target="{{ $target }}" wire:loading.remove>{{ $slot }}</span>
    @else
        {{ $slot }}
    @endif
</button>
