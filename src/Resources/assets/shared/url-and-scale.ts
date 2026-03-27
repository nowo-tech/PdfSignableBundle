/**
 * @fileoverview PDF URL resolution and scale (fit width / fit page). Shared with PdfTemplateBundle.
 */
if (typeof console !== 'undefined') console.debug('[PdfSignable] loaded: shared/url-and-scale.ts');
import type { IPdfDocForScale } from './types';
import { SCALE_GUTTER } from './constants';

/**
 * Returns the URL to use when loading a PDF.
 * Same-origin URLs are used as-is; cross-origin URLs go through the proxy.
 *
 * @param proxyUrl - Base URL for the PDF proxy endpoint (e.g. /pdf-signable/proxy)
 * @param url - Raw PDF URL from the form or user input
 * @returns URL suitable for pdfjsLib.getDocument()
 */
export function getLoadUrl(proxyUrl: string, url: string): string {
  try {
    const u = new URL(url, window.location.origin);
    return u.origin === window.location.origin ? url : proxyUrl + '?url=' + encodeURIComponent(url);
  } catch {
    return proxyUrl + '?url=' + encodeURIComponent(url);
  }
}

/**
 * Resolves the element whose width/height to use for scale (scroll area or container).
 * PdfSignable uses a .pdf-viewer-scroll child; PdfTemplate uses the container directly.
 *
 * @param container - Parent viewer container (e.g. #pdf-viewer-container)
 * @returns The element that has the visible scroll area (or container if no scroll child)
 */
function getScaleContainer(container: HTMLElement): HTMLElement {
  const scrollEl = container.querySelector('.pdf-viewer-scroll');
  return (scrollEl as HTMLElement) ?? container;
}

/**
 * Scale factor so the first page fits the viewer width (no horizontal scroll).
 * Uses requestAnimationFrame so layout is applied when the scroll wrapper exists (PdfSignable).
 *
 * @param pdfDoc - Loaded PDF document proxy (or null)
 * @param container - Viewer container (may contain .pdf-viewer-scroll)
 * @returns Scale factor (minimum 0.5, default 1.5 if unavailable)
 */
export async function getScaleForFitWidth(
  pdfDoc: IPdfDocForScale | null,
  container: HTMLElement | null
): Promise<number> {
  if (!pdfDoc || !container) return 1.5;
  await new Promise<void>((r) => requestAnimationFrame(() => r()));
  const page = await pdfDoc.getPage(1);
  const vp = page.getViewport({ scale: 1 });
  const el = getScaleContainer(container);
  const w = Math.max(0, el.clientWidth - SCALE_GUTTER);
  return w <= 0 ? 1.5 : Math.max(0.5, w / vp.width);
}

/**
 * Scale factor so the first page fits entirely in the viewer (fit page).
 *
 * @param pdfDoc - Loaded PDF document proxy (or null)
 * @param container - Viewer container (may contain .pdf-viewer-scroll)
 * @returns Scale factor (minimum 0.5, default 1.5 if unavailable)
 */
export async function getScaleForFitPage(
  pdfDoc: IPdfDocForScale | null,
  container: HTMLElement | null
): Promise<number> {
  if (!pdfDoc || !container) return 1.5;
  await new Promise<void>((r) => requestAnimationFrame(() => r()));
  const page = await pdfDoc.getPage(1);
  const vp = page.getViewport({ scale: 1 });
  const el = getScaleContainer(container);
  const cw = Math.max(0, el.clientWidth - SCALE_GUTTER);
  const ch = Math.max(0, el.clientHeight - SCALE_GUTTER);
  if (cw <= 0 || ch <= 0) return 1.5;
  const scaleW = cw / vp.width;
  const scaleH = ch / vp.height;
  return Math.max(0.5, Math.min(scaleW, scaleH));
}
