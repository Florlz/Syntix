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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
                condensed: ['"Barlow Condensed"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                background: 'var(--background)',
                foreground: 'var(--foreground)',
                surface: 'var(--surface)',
                'surface-muted': 'var(--surface-muted)',
                muted: 'var(--muted)',
                border: 'var(--border)',
                primary: 'var(--primary)',
                'primary-hover': 'var(--primary-hover)',
                'primary-foreground': 'var(--primary-foreground)',
                accent: 'var(--accent)',
                'accent-foreground': 'var(--accent-foreground)',
                danger: 'var(--danger)',
                'danger-surface': 'var(--danger-surface)',
                sidebar: 'var(--sidebar)',
            },
        },
    },

    plugins: [forms],
};
