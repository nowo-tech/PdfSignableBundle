import { defineConfig } from 'vite';
import { createHash } from 'crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, readdirSync, statSync, writeFileSync } from 'fs';
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
 * Deterministic source hash: a hash of the TypeScript/CSS sources and pnpm-lock.yaml. It only changes when the code or
 * the dependencies change; it is the change detector of buildTimeLabel() below.
 */
function sourceBuildId(dir: string): string {
  const root = new URL(`${dir}/`, import.meta.url);
  const hash = createHash('sha256');
  const files = (readdirSync(root, { recursive: true }) as string[])
    .filter((file) => !file.includes('.test.') && statSync(new URL(file, root)).isFile())
    .sort();
  for (const file of files) {
    hash.update(file).update(readFileSync(new URL(file, root)));
  }
  hash.update(readFileSync(new URL('pnpm-lock.yaml', import.meta.url)));

  return hash.digest('hex').slice(0, 12);
}

/**
 * Readable, reproducible build time for the "script loaded" console line ("2026-11-23 14:05 UTC").
 * The committed `build-info.json` ({ sourceHash, builtAt }) keeps it stable: when the source hash equals the stored
 * one, the stored `builtAt` is reused (a rebuild of unchanged sources never rewrites the committed assets); when it
 * differs, the new hash is stored with the current UTC minute. Commit `build-info.json` with the rebuilt assets: a CI
 * dist-freshness rebuild of a clean checkout then reproduces the same bytes, and fails visibly if it was forgotten.
 */
function buildTimeLabel(dir: string): string {
  const sourceHash = sourceBuildId(dir);
  const file = new URL('build-info.json', import.meta.url);
  let info: { sourceHash?: string; builtAt?: string } = {};
  try {
    info = JSON.parse(readFileSync(file, 'utf8')) as typeof info;
  } catch {
    // Missing or unreadable: written below.
  }
  if (info.sourceHash !== sourceHash || typeof info.builtAt !== 'string') {
    info = { sourceHash, builtAt: `${new Date().toISOString().slice(0, 16)}Z` };
    writeFileSync(file, `${JSON.stringify(info, null, 2)}\n`);
    console.warn(`build-info.json updated (${info.builtAt}): commit it together with the rebuilt assets.`);
  }

  return (info.builtAt as string).replace('T', ' ').replace('Z', ' UTC');
}

export default defineConfig({
  define: {
    __PDF_SIGNABLE_BUILD_TIME__: JSON.stringify(buildTimeLabel('src/Resources/assets')),
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
