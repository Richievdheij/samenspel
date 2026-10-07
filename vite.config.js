import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'

export default defineConfig({
  plugins: [
    laravel({
      input: ['resources/scss/main.scss', 'resources/js/app.js'],
      refresh: true,
    }),
  ],

  css: {
    preprocessorOptions: {
      // sass-embedded is Dart Sass. The modern compiler API is what @use and
      // @forward are designed against; the legacy one warns on every build.
      scss: { api: 'modern-compiler' },
    },
  },

  server: {
    // The port is a variable because .env declares it, and strictPort is the
    // whole point: without it Vite silently moves to 5174 when 5173 is busy, and
    // you then load a page served by one Vite against a manifest written by
    // another — a blank screen with nothing in any log. Failing to start is the
    // better outcome, and `make status` says which process holds the port.
    port: Number(process.env.VITE_PORT ?? 5173),
    strictPort: true,
  },
})
