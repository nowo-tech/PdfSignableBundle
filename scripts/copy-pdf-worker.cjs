/**
 * Copies the pdfjs-dist worker from node_modules to the bundle public directory.
 * Keeps worker version in sync with the API (same package).
 * Output: src/Resources/public/js/pdf.worker.min.mjs
 */
const fs = require('fs');
const path = require('path');

const packageRoot = path.resolve(__dirname, '..');
const buildDir = path.join(packageRoot, 'node_modules', 'pdfjs-dist', 'build');
const outDir = path.join(packageRoot, 'src', 'Resources', 'public', 'js');
const outFile = path.join(outDir, 'pdf.worker.min.mjs');

const candidates = ['pdf.worker.min.mjs', 'pdf.worker.mjs', 'pdf.worker.min.js', 'pdf.worker.js'];

let copied = false;
for (const name of candidates) {
  const src = path.join(buildDir, name);
  if (fs.existsSync(src)) {
    fs.mkdirSync(outDir, { recursive: true });
    fs.copyFileSync(src, outFile);
    console.log('[copy-pdf-worker] Copied', name, '->', outFile);
    copied = true;
    break;
  }
}

if (!copied) {
  console.error('[copy-pdf-worker] No worker file found in', buildDir);
  process.exit(1);
}
