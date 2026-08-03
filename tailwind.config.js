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
                floor: '#f7f7f9',
                'on-secondary-container': '#646468',
                'surface-tint': '#be0c17',
                'on-primary-container': '#fff3f2',
                'surface-variant': '#f3f4f6',
                'on-primary-fixed-variant': '#93000c',
                'on-surface': '#1f2023',
                outline: '#d1d5db',
                'secondary-container': '#e3e2e6',
                'primary-fixed': '#ffdad6',
                'on-secondary-fixed': '#1a1b1e',
                'on-primary': '#ffffff',
                'surface-bright': '#ffffff',
                'on-tertiary-fixed': '#241a00',
                'surface-container': '#f3f4f6',
                'on-background': '#1f2023',
                'on-secondary': '#ffffff',
                'inverse-on-surface': '#ebf1ff',
                'surface-container-low': '#f9fafb',
                'on-tertiary-container': '#4f3d00',
                'secondary-fixed-dim': '#c7c6ca',
                'on-primary-fixed': '#410002',
                'on-error': '#ffffff',
                background: '#f7f7f9',
                'error-container': '#ffdad6',
                'surface-container-lowest': '#ffffff',
                'on-surface-variant': '#6b7280',
                'primary-fixed-dim': '#ffb4ab',
                'outline-variant': '#e5e7eb',
                surface: '#ffffff',
                'inverse-primary': '#ffb4ab',
                'tertiary-fixed-dim': '#ecc246',
                'secondary-fixed': '#e3e2e6',
                tertiary: '#755b00',
                'on-error-container': '#93000a',
                error: '#ef4444',
                'surface-container-high': '#f3f4f6',
                'on-tertiary-fixed-variant': '#584400',
                'inverse-surface': '#2a313d',
                'tertiary-container': '#cea62c',
                'surface-container-highest': '#e5e7eb',
                'on-secondary-fixed-variant': '#46474a',
                'primary-container': '#d92727',
                secondary: '#5e5e62',
                'surface-dim': '#d3daea',
                'on-tertiary': '#ffffff',
                primary: '#b40012',
                'tertiary-fixed': '#ffe08e',
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
            },

            fontSize: {
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
            },
        },
    },

    plugins: [
        forms({ strategy: 'class' }),
        scrollbar({ nocompatible: true }),
        plugin(function ({ addUtilities }) {
            const typeScale = {
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
