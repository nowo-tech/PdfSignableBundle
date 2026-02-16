/**
 * Ambient declaration for dynamic import('pdfjs-dist').
 * Types are minimal; full types come from pdfjs-dist when installed.
 */
declare module 'pdfjs-dist' {
  export const GlobalWorkerOptions: { workerSrc: string };
  export function getDocument(url: string): { promise: Promise<unknown> };
}
