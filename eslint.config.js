const js = require("@eslint/js");

module.exports = [
  {
    // Bibliothèques tierces embarquées (minifiées, maintenues en amont).
    ignores: ["assets/js/vendor/**"],
  },
  js.configs.recommended,
  {
    files: ["assets/js/**/*.js"],
    languageOptions: {
      ecmaVersion: 2021,
      sourceType: "script",
      globals: {
        window: "readonly",
        document: "readonly",
        navigator: "readonly",
        jQuery: "readonly",
        wp: "readonly",
        fetch: "readonly",
        FormData: "readonly",
        URLSearchParams: "readonly",
        FileReader: "readonly",
        Option: "readonly",
        Promise: "readonly",
        setTimeout: "readonly",
        clearTimeout: "readonly",
        requestAnimationFrame: "readonly",
        encodeURIComponent: "readonly",
        serverSideRender: "readonly",
        saitoCeFrontend: "readonly",
        saitoCeAdmin: "readonly",
        saitoCeBlockData: "readonly",
      },
    },
  },
];
