import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontSize: {
                // Accessibility-oriented text scale for broad age range users.
                xs: ['1.0rem', { lineHeight: '1.35rem' }],
                sm: ['1.05rem', { lineHeight: '1.5rem' }],
                base: ['1.0625rem', { lineHeight: '1.65rem' }],
            },
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
        },
    },

    plugins: [forms],
};
