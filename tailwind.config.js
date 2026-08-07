import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';
import scrollbar from 'tailwind-scrollbar';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
        './resources/css/**/*.css',
    ],

    darkMode: 'class',

    theme: {
        extend: {
            colors: {
                floor: '#f4f6f7',
                'on-secondary-container': '#3d4a4c',
                'surface-tint': '#be0c17',
                'on-primary-container': '#fff3f2',
                'surface-variant': '#eef1f2',
                'on-primary-fixed-variant': '#93000c',
                'on-surface': '#1a1f21',
                outline: '#c9d0d2',
                'secondary-container': '#d0e8eb',
                'primary-fixed': '#ffdad6',
                'on-secondary-fixed': '#002022',
                'on-primary': '#ffffff',
                'surface-bright': '#ffffff',
                'on-tertiary-fixed': '#241a00',
                'surface-container': '#eef1f2',
                'on-background': '#1a1f21',
                'on-secondary': '#ffffff',
                'inverse-on-surface': '#f0f4f5',
                'surface-container-low': '#f7f9fa',
                'on-tertiary-container': '#4f3d00',
                'secondary-fixed-dim': '#8ecdd4',
                'on-primary-fixed': '#410002',
                'on-error': '#ffffff',
                background: '#f4f6f7',
                'error-container': '#ffdad6',
                'surface-container-lowest': '#ffffff',
                'on-surface-variant': '#5a6568',
                'primary-fixed-dim': '#ffb4ab',
                'outline-variant': '#dde3e5',
                surface: '#ffffff',
                'inverse-primary': '#ffb4ab',
                'tertiary-fixed-dim': '#ecc246',
                'secondary-fixed': '#b8dde2',
                tertiary: '#755b00',
                'on-error-container': '#93000a',
                error: '#ef4444',
                'surface-container-high': '#e8ecee',
                'on-tertiary-fixed-variant': '#584400',
                'inverse-surface': '#2a3134',
                'tertiary-container': '#cea62c',
                'surface-container-highest': '#e0e6e8',
                'on-secondary-fixed-variant': '#2a5256',
                'primary-container': '#d92727',
                secondary: '#03757f',
                'surface-dim': '#d5dcde',
                'on-tertiary': '#ffffff',
                primary: '#b40012',
                'tertiary-fixed': '#ffe08e',
                /* Explicit trust alias (same teal family) */
                trust: '#03757f',
                'trust-soft': '#e6f4f5',
                'trust-strong': '#02555c',
            },

            borderRadius: {
                sm: '0.25rem',
                DEFAULT: '0.5rem',
                md: '0.75rem',
                lg: '1rem',
                xl: '1.5rem',
                full: '9999px',
            },

            spacing: {
                base: '4px',
                xs: '8px',
                sm: '12px',
                md: '16px',
                lg: '24px',
                xl: '32px',
                '2xl': '48px',
                gutter: '24px',
                margin: '32px',
            },

            fontFamily: {
                sans: ['Inter', 'ui-sans-serif', 'system-ui', 'sans-serif'],
                display: ['"Playfair Display"', 'Georgia', 'ui-serif', 'serif'],
            },

            fontSize: {
                'display-xl': ['48px', { lineHeight: '56px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'headline-xl': ['32px', { lineHeight: '40px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'headline-xl-mobile': ['28px', { lineHeight: '36px', letterSpacing: '-0.02em', fontWeight: '700' }],
                'headline-lg': ['24px', { lineHeight: '32px', letterSpacing: '-0.015em', fontWeight: '600' }],
                'headline-md': ['18px', { lineHeight: '28px', letterSpacing: '-0.01em', fontWeight: '600' }],
                'body-md': ['14px', { lineHeight: '20px', letterSpacing: '0em', fontWeight: '400' }],
                'body-sm': ['12px', { lineHeight: '18px', letterSpacing: '0.01em', fontWeight: '400' }],
                'label-md': ['14px', { lineHeight: '20px', letterSpacing: '0.01em', fontWeight: '500' }],
                'label-sm': ['12px', { lineHeight: '16px', letterSpacing: '0.05em', fontWeight: '600' }],
            },

            boxShadow: {
                card: '0px 4px 12px rgba(0, 0, 0, 0.05)',
                'card-sm': '0px 4px 12px rgba(0, 0, 0, 0.02)',
                primary: '0px 4px 12px rgba(217, 39, 39, 0.2)',
                trust: '0px 4px 16px rgba(3, 117, 127, 0.18)',
            },

            keyframes: {
                'fade-up': {
                    '0%': { opacity: '0', transform: 'translateY(12px)' },
                    '100%': { opacity: '1', transform: 'translateY(0)' },
                },
                'hero-float': {
                    '0%, 100%': { transform: 'translateY(0)' },
                    '50%': { transform: 'translateY(-8px)' },
                },
            },

            animation: {
                'fade-up': 'fade-up 0.6s ease-out forwards',
                'hero-float': 'hero-float 5s ease-in-out infinite',
            },
        },
    },

    plugins: [
        forms({ strategy: 'class' }),
        scrollbar({ nocompatible: true }),
        plugin(function ({ addUtilities }) {
            const typeScale = {
                'display-xl': ['48px', '56px', '-0.02em', '700'],
                'headline-xl': ['32px', '40px', '-0.02em', '700'],
                'headline-xl-mobile': ['28px', '36px', '-0.02em', '700'],
                'headline-lg': ['24px', '32px', '-0.015em', '600'],
                'headline-md': ['18px', '28px', '-0.01em', '600'],
                'body-md': ['14px', '20px', '0em', '400'],
                'body-sm': ['12px', '18px', '0.01em', '400'],
                'label-md': ['14px', '20px', '0.01em', '500'],
                'label-sm': ['12px', '16px', '0.05em', '600'],
            };

            const utilities = {};
            for (const [name, [size, lineHeight, tracking, weight]] of Object.entries(typeScale)) {
                utilities[`.font-${name}`] = {
                    fontSize: size,
                    lineHeight,
                    letterSpacing: tracking,
                    fontWeight: weight,
                };
            }
            addUtilities(utilities);
        }),
    ],
};
