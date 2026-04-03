/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['"Plus Jakarta Sans"', 'system-ui', 'sans-serif'],
      },
      colors: {
        ink: {
          50: '#f7f8fa',
          100: '#eceef2',
          200: '#d5dae3',
          300: '#b3bcc9',
          400: '#8c99ad',
          500: '#6b7a90',
          600: '#566274',
          700: '#474f5e',
          800: '#3d434f',
          900: '#2a2e36',
        },
      },
      boxShadow: {
        soft: '0 2px 8px -2px rgb(15 23 42 / 0.06), 0 8px 24px -4px rgb(15 23 42 / 0.08)',
        card: '0 1px 2px rgb(15 23 42 / 0.04), 0 12px 32px -8px rgb(15 23 42 / 0.12)',
      },
    },
  },
  plugins: [],
}
