/**
 * @fileoverview PDF.js library resolution: worker URL and getDocument. Shared by signable and acroform.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/pdfjs-loader.ts');
import type { PDFDocumentProxy } from './types';

export type PdfJsLib = {
  getDocument(src: string | { data: ArrayBuffer }): { promise: Promise<PDFDocumentProxy> };
  GlobalWorkerOptions?: { workerSrc: string };
};

const DEFAULT_PDFJS_WORKER_CDN =
  'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

/**
 * Returns an absolute URL for the worker so loading works from any base (SPA, CDN, etc.).
 */
function toAbsoluteWorkerUrl(url: string): string {
  if (typeof window === 'undefined' || typeof URL === 'undefined') return url;
  if (url.startsWith('http://') || url.startsWith('https://') || url.startsWith('//')) return url;
  const base = window.location.origin;
  return url.startsWith('/') ? base + url : base + '/' + url;
}

export function getWorkerUrl(config: { pdfjsSource?: string; pdfjsWorkerUrl?: string }): string {
  const useNpm = config.pdfjsSource !== 'cdn';
  if (useNpm) {
    const fromConfig = config.pdfjsWorkerUrl && config.pdfjsWorkerUrl !== '';
    if (fromConfig) {
      if (config.pdfjsWorkerUrl!.includes('3.11.174')) {
        throw new Error(
          '[PdfSignable] With pdfjs_source "npm" do not use the 3.11 CDN worker. Run pnpm run copy-worker and use the bundle asset.',
        );
      }
      return toAbsoluteWorkerUrl(config.pdfjsWorkerUrl!);
    }
    const script = typeof document !== 'undefined' ? document.currentScript : null;
    let scriptSrc =
      script && 'src' in script && typeof (script as HTMLScriptElement).src === 'string'
        ? (script as HTMLScriptElement).src
        : '';
    if (!scriptSrc && typeof document !== 'undefined') {
      const bundleScript = document.querySelector<HTMLScriptElement>(
        'script[src*="pdf-signable.js"], script[src*="acroform-editor.js"]',
      );
      scriptSrc = bundleScript?.src ?? '';
    }
    if (scriptSrc) return toAbsoluteWorkerUrl(scriptSrc.replace(/\/[^/]*$/, '/pdf.worker.min.js'));
    // Fallback: default bundle asset path when script detection fails (e.g. dynamic load, SPA)
    const defaultWorkerPath = '/bundles/nowopdfsignable/js/pdf.worker.min.js';
    return toAbsoluteWorkerUrl(defaultWorkerPath);
  }
  if (config.pdfjsWorkerUrl && config.pdfjsWorkerUrl !== '') return config.pdfjsWorkerUrl;
  return DEFAULT_PDFJS_WORKER_CDN;
}

export async function getPdfJsLib(config: {
  pdfjsSource?: string;
  pdfjsWorkerUrl?: string;
}): Promise<PdfJsLib> {
  const workerUrl = getWorkerUrl(config);
  const useNpm = config.pdfjsSource !== 'cdn';
  if (useNpm) {
    const mod = await import('pdfjs-dist');
    if (mod.GlobalWorkerOptions) mod.GlobalWorkerOptions.workerSrc = workerUrl;
    return mod as unknown as PdfJsLib;
  }
  const g = (typeof window !== 'undefined' ? window : {}) as Window & { pdfjsLib?: PdfJsLib };
  if (typeof g.pdfjsLib === 'undefined') {
    throw new Error(
      '[PdfSignable] PDF.js not loaded. Include the CDN script or use default pdfjs_source (npm).',
    );
  }
  if (g.pdfjsLib.GlobalWorkerOptions) g.pdfjsLib.GlobalWorkerOptions.workerSrc = workerUrl;
  return g.pdfjsLib;
}
