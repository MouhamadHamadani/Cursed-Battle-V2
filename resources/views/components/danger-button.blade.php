<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center px-4 py-2 bg-black border border-red-500 font-newRocker uppercase tracking-widest text-red-500 hover:bg-red-900 hover:text-white transition ease-in-out duration-300']) }}>
    {{ $slot }}
</button>
