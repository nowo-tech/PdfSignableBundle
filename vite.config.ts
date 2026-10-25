import { defineConfig } from 'vite';
import { createHash } from 'crypto';
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, statSync } from 'fs';
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

/**
 * Deterministic build id: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change, so rebuilding unchanged sources (e.g. in `make release-check`) leaves the committed bundle
 * byte-identical (the former wall-clock stamp rewrote it on every build).
 */
function sourceBuildId(dir: string): string {
  const hash = createHash('sha256');
  const files = (readdirSync(dir, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(join(dir, file)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(join(dir, file)));
  }
  hash.update(readFileSync(join(__dirname, 'pnpm-lock.yaml')));

  return hash.digest('hex').slice(0, 12);
}

export default defineConfig({
  define: {
    __PDF_SIGNABLE_BUILD_TIME__: JSON.stringify(sourceBuildId(join(__dirname, 'src', 'Resources', 'assets'))),
  },
  build: {
    outDir: 'src/Resources/public/js',
    emptyOutDir: isPdf,
    rollupOptions: {
      input: isPdf
        ? { 'pdf-signable': 'src/Resources/assets/signable-editor.ts' }
        : { 'acroform-editor': 'src/Resources/assets/acroform-editor.ts' },
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
