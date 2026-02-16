import { defineConfig } from 'vite';
import { copyFileSync, existsSync, mkdirSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Single entry per build (Vite 5 does not accept config array; Rollup does not allow IIFE + code-splitting).
 * Run twice: vite build (pdf-signable) and VITE_ENTRY=acroform-editor vite build.
 * CSS is built separately via `pnpm run build:css` (Sass).
 * The pdf-signable build copies the PDF.js worker from node_modules to outDir so it is served next to pdf-signable.js (default asset).
 */
const entry = process.env.VITE_ENTRY || 'pdf-signable';
const isPdf = entry === 'pdf-signable';

const WORKER_CANDIDATES = [
  'pdf.worker.min.mjs',
  'pdf.worker.mjs',
  'pdf.worker.min.js',
  'pdf.worker.js',
];

const WORKER_OUT_NAME = 'pdf.worker.min.js';

function copyPdfWorker(outDir: string): void {
  const buildDir = join(__dirname, 'node_modules', 'pdfjs-dist', 'build');
  const outFile = join(outDir, WORKER_OUT_NAME);
  for (const name of WORKER_CANDIDATES) {
    const src = join(buildDir, name);
    if (existsSync(src)) {
      mkdirSync(outDir, { recursive: true });
      copyFileSync(src, outFile);
      console.log('[vite] Copied PDF.js worker:', name, '->', outFile);
      return;
    }
  }
  console.warn('[vite] PDF.js worker not found in', buildDir, '- run pnpm install');
}

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
  plugins: [
    isPdf
      ? {
          name: 'copy-pdf-worker',
          closeBundle() {
            const outDir = join(__dirname, 'src', 'Resources', 'public', 'js');
            copyPdfWorker(outDir);
          },
        }
      : undefined,
  ].filter(Boolean),
});
