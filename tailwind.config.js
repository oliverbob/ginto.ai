module.exports = {
  content: [
    "./src/**/*.php",
    "./public/**/*.php",
    "./public/**/*.html",
    "./public/assets/js/**/*.js",
    // public/pma/{libraries,templates,vendor,...} are symlinks into
    // vendor/phpmyadmin/phpmyadmin. Globs follow symlinks, so without this the
    // scan walks 6,248 phpMyAdmin files — a 3 second build becomes 25+ minutes,
    // and phpMyAdmin has no Tailwind classes to find.
    "!./public/pma/**",
  ],
  darkMode: 'class',
  theme: {
    extend: {
      // Used by silverqueen/dashboard.php, gtb/pages/settings.php and
      // public/index.html. Recovered from the previously built tailwind.css —
      // they were never in this file, so a rebuild without them silently
      // dropped .bg-primary, .text-primary and friends.
      colors: {
        primary: '#6366f1',
        secondary: '#8b5cf6',
      },
    },
  },
  plugins: [],
}
