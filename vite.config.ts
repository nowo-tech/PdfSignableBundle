import { defineConfig } from 'vite';

/**
 * Single entry per build (Vite 5 does not accept config array; Rollup does not allow IIFE + code-splitting).
 * Run twice: vite build (pdf-signable) and VITE_ENTRY=acroform-editor vite build.
 * CSS is built separately via `pnpm run build:css` (Sass).
 */
const entry = process.env.VITE_ENTRY || 'pdf-signable';
const isPdf = entry === 'pdf-signable';

export default defineConfig({
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: isPdf,
    rollupOptions: {
      input: isPdf
        ? { 'pdf-signable': 'assets/signable-editor.ts' }
        : { 'acroform-editor': 'assets/acroform-editor.ts' },
      output: {
        format: 'iife',
        ...(isPdf ? { inlineDynamicImports: true } : {}),
        entryFileNames: '[name].js',
        assetFileNames: '[name][extname]',
      },
    },
  },
});
