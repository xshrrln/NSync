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
            fontSize: {
                'base': '1rem',
                'sm': '0.938rem',
            },
            colors: {
// NSync Brand Colors - White + Green theme
                'nsync-green': {
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
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