import { defineConfig } from 'vite';

/**
 * Vite config for the Symfony 8 demo: builds assets/app.ts to public/build with manifest.
 */
export default defineConfig({
  build: {
    manifest: true,
    outDir: 'public/build',
    rollupOptions: {
      input: 'assets/app.ts',
    },
  },
});
