import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                // Primary - Deep Ocean Blue
                primary: {
                    50: '#EFF8FF',
                    100: '#DBF0FE',
                    200: '#BFE3FE',
                    300: '#93D1FC',
                    400: '#5FB6F8',
                    500: '#3B95F3',
                    600: '#1E75E8',
                    700: '#175FD5',
                    800: '#184EAD',
                    900: '#1A4488',
                    950: '#142B53',
                },
                // Secondary - Warm Gold
                secondary: {
                    50: '#FFFBEB',
                    100: '#FEF3C7',
                    200: '#FDE68A',
                    300: '#FCD34D',
                    400: '#FBBF24',
                    500: '#F59E0B',
                    600: '#D97706',
                    700: '#B45309',
                    800: '#92400E',
                    900: '#78350F',
                    950: '#451A03',
                },
                // Accent - Teal
                accent: {
                    50: '#F0FDFA',
                    100: '#CCFBF1',
                    200: '#99F6E4',
                    300: '#5EEAD4',
                    400: '#2DD4BF',
                    500: '#14B8A6',
                    600: '#0D9488',
                    700: '#0F766E',
                    800: '#115E59',
                    900: '#134E4A',
                    950: '#042F2E',
                },
            },
            fontFamily: {
                sans: ['DM Sans', ...defaultTheme.fontFamily.sans],
                display: ['Outfit', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },
            boxShadow: {
                soft: '0 2px 15px -3px rgba(0, 0, 0, 0.07), 0 10px 20px -2px rgba(0, 0, 0, 0.04)',
                'soft-lg': '0 10px 40px -15px rgba(0, 0, 0, 0.1)',
                glow: '0 0 20px rgba(59, 149, 243, 0.3)',
                elevated:
                    '0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -6px rgba(15, 23, 42, 0.12)',
                'elevated-lg':
                    '0 4px 6px -2px rgba(15, 23, 42, 0.05), 0 20px 48px -12px rgba(15, 23, 42, 0.16)',
            },
            animation: {
                'fade-in': 'fadeIn 0.5s ease-out',
                'fade-in-up': 'fadeInUp 0.5s ease-out',
                'slide-in-right': 'slideInRight 0.3s ease-out',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: '0' },
                    '100%': { opacity: '1' },
                },
                fadeInUp: {
                    '0%': { opacity: '0', transform: 'translateY(10px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                slideInRight: {
                    '0%': { opacity: '0', transform: 'translateX(20px)' },
                    '100%': { opacity: '1', transform: 'translateX(0)' },
                },
            },
        },
    },

    plugins: [forms, typography],
};
