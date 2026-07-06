/** @type {import('tailwindcss').Config} */
export default {
    // Scope every utility under the app root so it never leaks into wp-admin.
    important: '#hubgo-settings-app',
    content: [
        './src/**/*.{vue,js}',
    ],
    // Disable preflight: we do not want Tailwind's global reset to touch wp-admin.
    corePlugins: {
        preflight: false,
    },
    theme: {
        extend: {
            colors: {
                primary: {
                    DEFAULT: '#008aff',
                    50: '#e6f3ff',
                    100: '#cce7ff',
                    500: '#008aff',
                    600: '#0070d1',
                    700: '#0059a6',
                },
            },
        },
    },
    plugins: [],
};
