import js from '@eslint/js'
import globals from 'globals'
import prettier from 'eslint-config-prettier/flat'

/**
 * Flat config. Order matters: later entries win, which is why the formatter
 * disable block is last and nothing may be appended after it.
 *
 * PHP is not linted here and does not need to be. What eslint's type-aware rules
 * catch, PHPStan catches; what its stylistic rules catch, Pint fixes. So
 * `make lint` is the JavaScript half and `make analyse` is the PHP half.
 */
export default [
  {
    ignores: [
      'vendor/**',
      'node_modules/**',
      'public/build/**',
      'public/hot',
      'storage/**',
      'bootstrap/cache/**',
    ],
  },

  js.configs.recommended,

  {
    // Browser code shipped through Vite.
    files: ['resources/js/**/*.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: globals.browser,
    },
  },

  {
    // Build configuration and the repo's own tooling, which run under Node.
    files: ['scripts/**/*.mjs', '*.config.js'],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: globals.node,
    },
    rules: {
      'no-console': 'off',
    },
  },

  // LAST, and nothing goes after it: this turns off every rule whose opinion
  // Prettier already holds, so the two tools cannot disagree about a line.
  prettier,
]
