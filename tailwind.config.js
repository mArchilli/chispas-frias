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
            fontFamily: {
                sans: ['Poppins', ...defaultTheme.fontFamily.sans],
                display: ['Poppins', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                navy: {
                    700: '#1D4E89',
                    900: '#0A1F44',
                },
                ice: {
                    50: '#F2FAFC',
                    100: '#E6F6FA',
                    500: '#49C6E5',
                },
                background: '#F7F7F3',
                storefront: '#0066E6',
                surface: '#FFFFFF',
                graphite: '#1C2027',
                gray: {
                    200: '#DCE2E8',
                    500: '#7D899A',
                },
                promo: '#F2B84B',
            },
            boxShadow: {
                soft: '0 8px 24px rgba(10, 31, 68, 0.06)',
                card: '0 12px 35px rgba(10, 31, 68, 0.08)',
                'card-hover': '0 20px 45px rgba(10, 31, 68, 0.13)',
            },
            opacity: {
                12: '0.12',
                15: '0.15',
                35: '0.35',
                45: '0.45',
                55: '0.55',
                65: '0.65',
                85: '0.85',
            },
        },
    },

    plugins: [forms],
};
