import { defineConfig } from 'vite';

export default defineConfig({
  root: '.',
  publicDir: false,
  build: {
    outDir: 'public/build',
    emptyOutDir: true,
    manifest: true,
    rollupOptions: {
      input: {
        app: 'resources/js/app.js',
      },
    },
  },
  server: {
    origin: 'http://localhost:5173',
  },
});
