/** @type {import('tailwindcss').Config} */
export default {
  content: ["./index.html", "./src/**/*.{vue,js,ts,jsx,tsx}"],
  theme: {
    extend: {
      colors: {
        maroon: {
          DEFAULT: "#520000",
          dark: "#3A0000",
          light: "#7A1010",
        },
        accent: "#C17070",
        ink: "#1A1A1A",
        muted: "#555555",
        line: "#E5E5E5",
        surface: "#F9F9F9",
      },
      fontFamily: {
        sans: ["Inter", "sans-serif"],
      },
    },
  },
  plugins: [],
};
