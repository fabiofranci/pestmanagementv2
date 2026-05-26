module.exports = {
  content: [
    './resources/**/*.blade.php',
    './resources/**/*.js',
    './resources/**/*.css',
    './vendor/filament/**/*.blade.php',
  ],
  theme: {
    extend: {
      colors: {
        primary: '#0b6e4f',
        accent: '#f7b500',
        neutral: '#f6f7f9',
      },
    },
  },
  plugins: [],
};
