/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.{vue,js,jsx,ts,tsx}',
    ],
    theme: {
        extend: {
            colors: {
                primary: '#8B5CF6',
                secondary: '#EC4899',
            },
        },
    },
    plugins: [],
}
