import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    // This allows the site to automatically switch based on system theme
    darkMode: 'media', 

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // NSync Brand Colors
                'nsync-blue': {
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                },
                // Custom slate overrides for deeper dark mode
                'nsync-slate': {
                    900: '#0f172a',
                    950: '#020617',
                }
            },
        },
    },

    plugins: [forms],
};