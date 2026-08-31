import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                // Ported from V1's "leather and gold" theme — display faces only.
                uncialAntiqua: ['UncialAntiqua'],
                newRocker: ['NewRocker-Regular'],
            },
            textShadow: {
                sm: '0 0 2px var(--tw-shadow-color)',
                DEFAULT: '0 0 4px var(--tw-shadow-color)',
                lg: '0 0 16px var(--tw-shadow-color)',
            },
        },
    },

    plugins: [
        forms,
        // text-shadow-{sm,DEFAULT,lg} paired with shadow-{color} — V1 uses this
        // for the glowing gold page titles.
        plugin(function ({ matchUtilities, theme }) {
            matchUtilities(
                { 'text-shadow': (value) => ({ textShadow: value }) },
                { values: theme('textShadow') }
            );
        }),
    ],
};
