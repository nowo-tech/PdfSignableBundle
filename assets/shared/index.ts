/**
 * Shared modules for signable and acroform viewers.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/index.ts');
export { SCALE_GUTTER } from './constants';
export type { IPdfDocForScale, NowoPdfSignableConfig, PDFViewport, PDFPageProxy, PDFDocumentProxy } from './types';
export { getLoadUrl, getScaleForFitWidth, getScaleForFitPage } from './url-and-scale';
export type { ZoomToolbarOptions } from './zoom-toolbar';
export { bindZoomToolbar } from './zoom-toolbar';
export { getPdfJsLib, getWorkerUrl } from './pdfjs-loader';
export type { PdfJsLib } from './pdfjs-loader';
