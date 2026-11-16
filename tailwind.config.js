import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

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
                'hsi-navy': '#071B34',
                'hsi-navy-soft': '#0B2748',
                'hsi-gold': '#D4AF37',
                'hsi-gold-light': '#E8C766',
                'hsi-cream': '#F8F6F0',
                'hsi-white': '#FFFFFF',
                'hsi-slate': '#475569',
                'hsi-slate-light': '#94A3B8',
                'hsi-border': '#E5E1D8',
                'hsi-muted': '#F3F0E8',
                'hsi-success': '#16A34A',
                'hsi-warning': '#D97706',
                'hsi-danger': '#DC2626',
                'hsi-info': '#2563EB',
            },
            fontFamily: {
                heading: ['Playfair Display', ...defaultTheme.fontFamily.serif],
                body: ['Inter', ...defaultTheme.fontFamily.sans],
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                hsi: '0 8px 24px rgba(7, 27, 52, 0.06)',
                hsiSoft: '0 4px 14px rgba(7, 27, 52, 0.05)',
            },
            borderRadius: {
                hsi: '18px',
            },
        },
    },

    plugins: [forms],
};
