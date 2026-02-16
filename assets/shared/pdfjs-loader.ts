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
      return config.pdfjsWorkerUrl!;
    }
    const script = typeof document !== 'undefined' ? document.currentScript : null;
    const scriptSrc =
      script && 'src' in script && typeof (script as HTMLScriptElement).src === 'string'
        ? (script as HTMLScriptElement).src
        : '';
    if (scriptSrc) return scriptSrc.replace(/\/[^/]*$/, '/pdf.worker.min.mjs');
    throw new Error(
      '[PdfSignable] With pdfjsSource "npm" could not resolve worker URL. Set pdfjs_worker_url or run pnpm run copy-worker.',
    );
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
