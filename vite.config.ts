import { defineConfig } from 'vite';

/**
 * Vite config for the bundle: builds assets/pdf-signable.ts to an IIFE (pdf-signable.js) in src/Resources/public/js.
 */
export default defineConfig({
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: true,
    rollupOptions: {
      input: 'assets/pdf-signable.ts',
      output: {
        format: 'iife',
        entryFileNames: 'pdf-signable.js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
