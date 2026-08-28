/** @type {import('tailwindcss').Config} */
export default {
    // Scope every utility under the app root so it never leaks into wp-admin.
    // A class (not the mount id) so teleported roots — modals, toasts, select
    // dropdowns rendered into <body> — can opt in by carrying `.hubgo-app` too.
    important: '.hubgo-app',
    content: [
        './src/**/*.{vue,js}',
        '../admin/src/Views/**/*.php',
    ],
    // Disable preflight: we do not want Tailwind's global reset to touch wp-admin.
    corePlugins: {
        preflight: false,
    },
    theme: {
        extend: {
            colors: {
                // Brand scale anchored on the HubGo primary (#008aff at 700, the
                // shade used for solid fills, active tabs and primary buttons).
                primary: {
                    DEFAULT: '#008aff',
                    0: '#ffffff',
                    50: '#e6f2ff',
                    100: '#cce5ff',
                    200: '#99ccff',
                    300: '#66b2ff',
                    400: '#33a0ff',
                    500: '#1a95ff',
                    600: '#0a8fff',
                    700: '#008aff',
                    800: '#0069c2',
                    900: '#00396a',
                    950: '#00223f',
                },
                // Neutral chrome used by page backgrounds, muted labels and eyebrows.
                shell: {
                    50: '#f4f7fb',
                    100: '#e8eef8',
                    200: '#cddcf1',
                    300: '#a7c2e5',
                    400: '#7aa1d1',
                    500: '#4a78b4',
                    600: '#32558a',
                    700: '#27436b',
                    800: '#1f3656',
                    900: '#17253d',
                },
                ink: '#102033',
                success: '#22c55e',
                danger: '#ef4444',
                warning: '#ffba08',
                info: '#0ea5e9',
                dark: '#232323',
                panel: '#ffffff',
                muted: '#6b7280',
            },
            boxShadow: {
                soft: '0 18px 50px rgba(16, 32, 51, 0.12)',
            },
            borderRadius: {
                '2xl': '1.25rem',
            },
        },
    },
    plugins: [],
};
