/**
 * @fileoverview Shared types: config, PDF.js viewport/doc, scale. Used by signable and acroform viewers.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/types.ts');

export interface IPdfDocForScale {
  getPage(n: number): Promise<{
    getViewport(o: { scale: number }): { width: number; height: number; scale: number };
  }>;
}

export interface NowoPdfSignableConfig {
  proxyUrl: string;
  debug?: boolean;
  pdfjsSource?: 'cdn' | 'npm';
  pdfjsWorkerUrl?: string;
  strings: {
    error_load_pdf: string;
    pdf_not_found: string;
    alert_url_required: string;
    alert_submit_error: string;
    loading_state: string;
    load_pdf_btn: string;
    default_box_name: string;
    zoom_in?: string;
    zoom_out?: string;
    zoom_fit?: string;
    fit_width?: string;
    fit_page?: string;
    no_overlap_message?: string;
  };
}

declare global {
  interface Window {
    NowoPdfSignableConfig?: NowoPdfSignableConfig;
  }
}

export interface PDFViewport {
  scale: number;
  width: number;
  height: number;
  convertToPdfPoint(x: number, y: number): [number, number];
}

export interface PDFPageProxy {
  getViewport(params: { scale: number }): PDFViewport;
  render(params: {
    canvasContext: CanvasRenderingContext2D;
    viewport: PDFViewport;
  }): { promise: Promise<void> };
  getAnnotations?: () => Promise<unknown>;
}

export interface PDFDocumentProxy {
  numPages: number;
  getPage(num: number): Promise<PDFPageProxy>;
}
