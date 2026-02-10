/**
 * Types and config for PdfSignable bundle. PDF viewport/doc types are bundle-specific; scale logic uses shared IPdfDocForScale.
 */
/** Runtime config from Twig (window.NowoPdfSignableConfig). */
export interface NowoPdfSignableConfig {
  proxyUrl: string;
  debug?: boolean;
  strings: {
    error_load_pdf: string;
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

/** PDF.js viewport (from getViewport). */
export interface PDFViewport {
  scale: number;
  width: number;
  height: number;
  convertToPdfPoint(x: number, y: number): [number, number];
}

/** PDF.js page proxy. */
export interface PDFPageProxy {
  getViewport(params: { scale: number }): PDFViewport;
  render(params: {
    canvasContext: CanvasRenderingContext2D;
    viewport: PDFViewport;
  }): { promise: Promise<void> };
}

/** PDF.js document proxy. */
export interface PDFDocumentProxy {
  numPages: number;
  getPage(num: number): Promise<PDFPageProxy>;
}

/** Box bounds in form units (for overlap check). */
export interface BoxBounds {
  page: number;
  x: number;
  y: number;
  w: number;
  h: number;
}

