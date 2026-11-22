/** @type {import('tailwindcss').Config} */
export default {
  content: ['./index.html', './src/**/*.{vue,js,ts}'],
  theme: {
    extend: {
      colors: {
        paper: '#FAF8F5',
        subtle: '#F3F0EA',
        charcoal: {
          DEFAULT: '#121316',
          elevated: '#1A1C20',
          border: '#2B2E35',
        },
        crimson: {
          DEFAULT: '#BE123C',
          hover: '#9F1239',
          soft: '#FFE4E6',
        },
        ambercine: {
          DEFAULT: '#D97706',
          soft: '#FEF3C7',
        },
        emeraldok: {
          DEFAULT: '#15803D',
          soft: '#DCFCE7',
        },
        ink: {
          heading: '#1C1917',
          body: '#44403C',
          muted: '#78716C',
          slate: '#1E293B',
        },
      },
      fontFamily: {
        display: ['Fraunces', 'Georgia', 'serif'],
        sans: ['"Plus Jakarta Sans"', '-apple-system', 'BlinkMacSystemFont', 'sans-serif'],
        mono: ['"JetBrains Mono"', 'ui-monospace', 'SFMono-Regular', 'monospace'],
      },
    },
  },
  plugins: [],
};
