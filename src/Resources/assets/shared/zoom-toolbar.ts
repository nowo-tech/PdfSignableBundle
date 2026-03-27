/**
 * @fileoverview Zoom toolbar: bind zoom in/out and fit width/fit page. Shared by signable and acroform.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/zoom-toolbar.ts');

export interface ZoomToolbarOptions {
  onZoomOut: () => void;
  onZoomIn: () => void;
  onFitWidth: () => void | Promise<void>;
  onFitPage: () => void | Promise<void>;
  container?: HTMLElement | null;
}

export function bindZoomToolbar(options: ZoomToolbarOptions): void {
  const { onZoomOut, onZoomIn, onFitWidth, onFitPage, container } = options;
  container?.querySelector('#pdf-zoom-toolbar')?.remove();
  document.getElementById('pdfZoomOut')?.addEventListener('click', () => onZoomOut());
  document.getElementById('pdfZoomIn')?.addEventListener('click', () => onZoomIn());
  document.getElementById('pdfFitWidth')?.addEventListener('click', () => void onFitWidth());
  document.getElementById('pdfFitPage')?.addEventListener('click', () => void onFitPage());
}
