<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-black/60 border border-yellow-700 font-newRocker uppercase tracking-widest text-yellow-500 hover:bg-yellow-700 hover:text-black transition ease-in-out duration-300 disabled:cursor-not-allowed disabled:opacity-50']) }}>
    {{ $slot }}
</button>
